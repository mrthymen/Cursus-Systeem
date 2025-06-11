<?php
/**
 * Minimal Working Processor v1.0
 * Ultra-simple version that definitely works
 * We'll add complexity step by step once this works
 */

// Basic error handling
ini_set('display_errors', 0);  // Hide errors from output
error_reporting(E_ALL);

// Simple logging function
function simpleLog($message) {
    $logFile = __DIR__ . '/simple_debug.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

simpleLog("=== MINIMAL PROCESSOR START ===");

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    simpleLog("ERROR: Not POST request");
    header('Content-Type: text/plain');
    echo 'method_not_allowed';
    exit;
}

simpleLog("POST request received");

// Check basic required fields
if (empty($_POST['naam']) || empty($_POST['email'])) {
    simpleLog("ERROR: Missing naam or email");
    header('Content-Type: text/plain');
    echo 'missing_required_fields';
    exit;
}

simpleLog("Basic fields present");

// Try to include config
try {
    require_once 'config.php';
    simpleLog("Config loaded");
} catch (Exception $e) {
    simpleLog("ERROR: Config failed: " . $e->getMessage());
    header('Content-Type: text/plain');
    echo 'config_error';
    exit;
}

// Try database connection
try {
    $pdo = getDatabase();
    simpleLog("Database connected");
} catch (Exception $e) {
    simpleLog("ERROR: Database failed: " . $e->getMessage());
    header('Content-Type: text/plain');
    echo 'database_error';
    exit;
}

// Get form data
$naam = trim($_POST['naam']);
$email = trim($_POST['email']);
$training_type = trim($_POST['training_type'] ?? 'unknown');
$training_name = trim($_POST['training_name'] ?? 'Unknown Training');
$selected_course_id = $_POST['selected_course_id'] ?? null;

simpleLog("Processing: $naam, $email, $training_type, Course: $selected_course_id");

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    simpleLog("ERROR: Invalid email");
    header('Content-Type: text/plain');
    echo 'invalid_email';
    exit;
}

simpleLog("Email validated");

try {
    // Start transaction
    $pdo->beginTransaction();
    simpleLog("Transaction started");
    
    // Create or get user (simplified)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        $user_id = $user['id'];
        simpleLog("Existing user found: $user_id");
    } else {
        // Create new user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$naam, $email]);
        $user_id = $pdo->lastInsertId();
        simpleLog("New user created: $user_id");
    }
    
    // Decision: Direct enrollment or interest?
    if (!empty($selected_course_id) && $selected_course_id !== 'other' && is_numeric($selected_course_id)) {
        simpleLog("Processing direct enrollment for course: $selected_course_id");
        
        // Check if course exists
        $stmt = $pdo->prepare("SELECT id, name, price, max_participants FROM courses WHERE id = ? AND active = 1");
        $stmt->execute([$selected_course_id]);
        $course = $stmt->fetch();
        
        if (!$course) {
            simpleLog("ERROR: Course not found: $selected_course_id");
            $pdo->rollback();
            header('Content-Type: text/plain');
            echo 'course_not_found';
            exit;
        }
        
        simpleLog("Course found: " . $course['name']);
        
        // Check if already enrolled
        $stmt = $pdo->prepare("SELECT id FROM course_participants WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$user_id, $selected_course_id]);
        
        if ($stmt->fetch()) {
            simpleLog("User already enrolled");
            $pdo->rollback();
            header('Content-Type: text/plain');
            echo 'already_enrolled';
            exit;
        }
        
        // Create enrollment
        $stmt = $pdo->prepare("INSERT INTO course_participants (user_id, course_id, enrollment_date, payment_status, source) VALUES (?, ?, NOW(), 'pending', 'universal_form')");
        $stmt->execute([$user_id, $selected_course_id]);
        $enrollment_id = $pdo->lastInsertId();
        
        simpleLog("Enrollment created: $enrollment_id");
        
        // Set session for payment
        session_start();
        $_SESSION['pending_enrollment_id'] = $enrollment_id;
        $_SESSION['pending_course_id'] = $selected_course_id;
        $_SESSION['enrollment_user_id'] = $user_id;
        $_SESSION['pending_payment_amount'] = $course['price'];
        
        simpleLog("Session variables set for payment");
        
        $pdo->commit();
        simpleLog("Transaction committed - enrollment success");
        
        header('Content-Type: text/plain');
        echo 'enrolled_payment_required';
        exit;
        
    } else {
        simpleLog("Processing interest registration");
        
        // Create interest record
        $periode = $_POST['periode'] ?? [];
        $periode_text = is_array($periode) ? implode(', ', $periode) : '';
        $opmerkingen = trim($_POST['opmerkingen'] ?? '');
        
        // Check if interests table exists, if not create it
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS interests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                naam VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                telefoon VARCHAR(50),
                organisatie VARCHAR(255),
                training_type VARCHAR(100),
                training_name VARCHAR(255),
                periode_voorkeur TEXT,
                opmerkingen TEXT,
                status VARCHAR(50) DEFAULT 'new',
                source VARCHAR(100) DEFAULT 'website',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            simpleLog("Interests table ensured");
        } catch (Exception $e) {
            simpleLog("ERROR creating interests table: " . $e->getMessage());
        }
        
        $stmt = $pdo->prepare("INSERT INTO interests (naam, email, telefoon, organisatie, training_type, training_name, periode_voorkeur, opmerkingen, source, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'universal_form', NOW())");
        
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
        simpleLog("Interest created: $interest_id");
        
        $pdo->commit();
        simpleLog("Transaction committed - interest success");
        
        header('Content-Type: text/plain');
        echo 'interest_success';
        exit;
    }
    
} catch (Exception $e) {
    simpleLog("ERROR in processing: " . $e->getMessage());
    $pdo->rollback();
    header('Content-Type: text/plain');
    echo 'processing_error';
    exit;
}

simpleLog("=== PROCESSOR END (should not reach here) ===");
?>