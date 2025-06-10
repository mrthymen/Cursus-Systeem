<?php
/**
 * Enhanced Registration Processor - Cursus Systeem v6.2.0
 * Handles: Direct enrollment, Interest registration, Incompany requests
 * With smart course discovery and payment preparation
 * Updated: 2025-06-10
 * Fixes: Complete enrollment flow, session management, payment preparation
 */

session_start();
require_once 'config.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: text/plain; charset=utf-8');

// Security: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logActivity("Invalid request method: " . $_SERVER['REQUEST_METHOD'], 'SECURITY');
    exit('Method not allowed');
}

// Honeypot spam protection
if (!empty($_POST['website'])) {
    logActivity("Spam attempt detected from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'SECURITY');
    exit('spam');
}

// Rate limiting (simple implementation)
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "registration_" . md5($ip);
$rate_limit_file = sys_get_temp_dir() . "/" . $rate_limit_key;

if (file_exists($rate_limit_file)) {
    $last_request = filemtime($rate_limit_file);
    if (time() - $last_request < 10) { // 10 seconds between requests
        logActivity("Rate limit exceeded for IP: $ip", 'SECURITY');
        exit('rate_limit');
    }
}
touch($rate_limit_file);

// Get database connection
try {
    $pdo = getDatabase();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    logActivity("Database connection failed: " . $e->getMessage(), 'ERROR');
    exit('database_error');
}

// Validate required fields
$required_fields = ['training_type', 'training_name', 'naam', 'email'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        logActivity("Missing required field: $field", 'VALIDATION');
        exit("missing_field_$field");
    }
}

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
    
    // Incompany specific
    'aantal_deelnemers' => $_POST['aantal_deelnemers'] ?? null,
    'gewenste_periode' => trim($_POST['gewenste_periode'] ?? ''),
    'gewenste_locatie' => $_POST['gewenste_locatie'] ?? '',
    'budget_indicatie' => $_POST['budget_indicatie'] ?? '',
];

// Validate email
if (!$data['email']) {
    logActivity("Invalid email provided: " . ($_POST['email'] ?? 'empty'), 'VALIDATION');
    exit('invalid_email');
}

// Validate name
if (strlen($data['naam']) < 2) {
    logActivity("Invalid name provided: " . $data['naam'], 'VALIDATION');
    exit('invalid_name');
}

logActivity("Processing registration: {$data['training_type']} for {$data['naam']} ({$data['email']})", 'INFO');

// Helper functions
function createOrGetUser($pdo, $naam, $email, $telefoon = '', $organisatie = '') {
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            // Update existing user info
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    name = ?, telefoon = ?, organisatie = ?, updated_at = NOW()
                WHERE email = ?
            ");
            $stmt->execute([$naam, $telefoon, $organisatie, $email]);
            
            logActivity("Updated existing user ID: " . $existing_user['id'], 'INFO');
            return $existing_user['id'];
        } else {
            // Create new user
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, telefoon, organisatie, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$naam, $email, $telefoon, $organisatie]);
            
            $user_id = $pdo->lastInsertId();
            logActivity("Created new user ID: $user_id", 'INFO');
            return $user_id;
        }
    } catch (Exception $e) {
        error_log("Error creating/updating user: " . $e->getMessage());
        logActivity("Error creating/updating user: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function enrollUserInCourse($pdo, $user_id, $course_id) {
    try {
        // Check if already enrolled
        $stmt = $pdo->prepare("
            SELECT id FROM course_participants 
            WHERE user_id = ? AND course_id = ? AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) {
            logActivity("User $user_id already enrolled in course $course_id", 'WARNING');
            return 'already_enrolled';
        }
        
        // Get course info and check capacity
        $stmt = $pdo->prepare("
            SELECT 
                c.max_participants,
                c.price,
                c.name,
                COUNT(cp.id) as current_participants
            FROM courses c
            LEFT JOIN course_participants cp ON c.id = cp.course_id 
                AND cp.payment_status != 'cancelled'
            WHERE c.id = ? AND c.active = 1
            GROUP BY c.id
        ");
        $stmt->execute([$course_id]);
        $course_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$course_info) {
            logActivity("Course $course_id not found or inactive", 'ERROR');
            return 'course_not_found';
        }
        
        if ($course_info['current_participants'] >= $course_info['max_participants']) {
            logActivity("Course $course_id is full ({$course_info['current_participants']}/{$course_info['max_participants']})", 'WARNING');
            return 'course_full';
        }
        
        // Enroll user with pending payment status
        $stmt = $pdo->prepare("
            INSERT INTO course_participants 
            (user_id, course_id, enrollment_date, payment_status, source)
            VALUES (?, ?, NOW(), 'pending', 'universal_form')
        ");
        $stmt->execute([$user_id, $course_id]);
        
        $enrollment_id = $pdo->lastInsertId();
        logActivity("Enrolled user $user_id in course $course_id (enrollment ID: $enrollment_id)", 'INFO');
        
        return $enrollment_id;
        
    } catch (Exception $e) {
        error_log("Error enrolling user: " . $e->getMessage());
        logActivity("Error enrolling user: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function createInterest($pdo, $data) {
    try {
        $periode_text = is_array($data['periode']) ? implode(', ', $data['periode']) : '';
        
        // Check for existing interest (duplicate prevention)
        $stmt = $pdo->prepare("
            SELECT id FROM interests 
            WHERE email = ? AND training_name = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$data['email'], $data['training_name']]);
        
        if ($stmt->fetch()) {
            logActivity("Duplicate interest attempt for {$data['email']} - {$data['training_name']}", 'WARNING');
            return 'duplicate_interest';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO interests 
            (naam, email, telefoon, organisatie, training_type, training_name, 
             periode_voorkeur, opmerkingen, source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'universal_form', NOW())
        ");
        
        $stmt->execute([
            $data['naam'],
            $data['email'], 
            $data['telefoon'],
            $data['organisatie'],
            $data['training_type'],
            $data['training_name'],
            $periode_text,
            $data['opmerkingen']
        ]);
        
        $interest_id = $pdo->lastInsertId();
        logActivity("Created interest ID: $interest_id for {$data['training_name']}", 'INFO');
        
        return $interest_id;
        
    } catch (Exception $e) {
        error_log("Error creating interest: " . $e->getMessage());
        logActivity("Error creating interest: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function createIncompanyRequest($pdo, $data) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO incompany_requests 
            (naam, email, telefoon, organisatie, training_type, training_name,
             aantal_deelnemers, gewenste_periode, gewenste_locatie, budget_indicatie,
             opmerkingen, status, source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'universal_form', NOW())
        ");
        
        $stmt->execute([
            $data['naam'],
            $data['email'],
            $data['telefoon'], 
            $data['organisatie'],
            $data['training_type'],
            $data['training_name'],
            $data['aantal_deelnemers'],
            $data['gewenste_periode'],
            $data['gewenste_locatie'],
            $data['budget_indicatie'],
            $data['opmerkingen']
        ]);
        
        $request_id = $pdo->lastInsertId();
        logActivity("Created incompany request ID: $request_id", 'INFO');
        
        return $request_id;
        
    } catch (Exception $e) {
        error_log("Error creating incompany request: " . $e->getMessage());
        logActivity("Error creating incompany request: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function sendConfirmationEmail($type, $data, $result_id = null) {
    // TODO: Implement email sending logic
    // For now, just log the email that should be sent
    logActivity("Should send $type confirmation email to {$data['email']} for {$data['training_name']} (ID: $result_id)", 'INFO');
    return true;
}

function logActivity($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    error_log("[$timestamp] [$level] [$ip] $message");
}

// Main processing logic
try {
    $pdo->beginTransaction();
    
    // Create or get user
    $user_id = createOrGetUser($pdo, $data['naam'], $data['email'], $data['telefoon'], $data['organisatie']);
    if (!$user_id) {
        throw new Exception("Failed to create/update user");
    }
    
    $result = null;
    $response_type = null;
    
    // Process based on registration type and course selection
    if ($data['training_type'] === 'incompany') {
        // Incompany request
        $result = createIncompanyRequest($pdo, $data);
        if ($result) {
            sendConfirmationEmail('incompany', $data, $result);
            $response_type = 'incompany_created';
        } else {
            throw new Exception("Failed to create incompany request");
        }
        
    } else if (!empty($data['selected_course_id']) && $data['selected_course_id'] !== 'other' && is_numeric($data['selected_course_id'])) {
        // Direct course enrollment
        $enrollment_result = enrollUserInCourse($pdo, $user_id, $data['selected_course_id']);
        
        if (is_numeric($enrollment_result)) {
            // Successfully enrolled - prepare for payment
            sendConfirmationEmail('enrollment', $data, $enrollment_result);
            
            // Store enrollment and course info in session for payment processing
            $_SESSION['pending_enrollment_id'] = $enrollment_result;
            $_SESSION['pending_course_id'] = $data['selected_course_id'];
            $_SESSION['enrollment_user_id'] = $user_id;
            
            // Get course price for payment
            $stmt = $pdo->prepare("SELECT price FROM courses WHERE id = ?");
            $stmt->execute([$data['selected_course_id']]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($course) {
                $_SESSION['pending_payment_amount'] = $course['price'];
            }
            
            $response_type = 'enrolled';
            
            logActivity("Enrollment prepared for payment: enrollment_id=$enrollment_result, course_id={$data['selected_course_id']}, amount={$course['price']}", 'INFO');
            
        } else if ($enrollment_result === 'already_enrolled') {
            $response_type = 'already_enrolled';
        } else if ($enrollment_result === 'course_full') {
            // Course is full, create interest instead
            $interest_result = createInterest($pdo, $data);
            if ($interest_result && $interest_result !== 'duplicate_interest') {
                sendConfirmationEmail('waitlist', $data, $interest_result);
                $response_type = 'waitlist_created';
            } else if ($interest_result === 'duplicate_interest') {
                $response_type = 'duplicate_interest';
            } else {
                throw new Exception("Failed to create waitlist entry");
            }
        } else {
            throw new Exception("Failed to enroll user: $enrollment_result");
        }
        
    } else {
        // Interest registration (other moment or no specific course)
        $result = createInterest($pdo, $data);
        if ($result && $result !== 'duplicate_interest') {
            sendConfirmationEmail('interest', $data, $result);
            $response_type = 'interest_created';
        } else if ($result === 'duplicate_interest') {
            $response_type = 'duplicate_interest';
        } else {
            throw new Exception("Failed to create interest");
        }
    }
    
    $pdo->commit();
    
    // Log successful registration
    logActivity("Registration completed successfully: Type=$response_type, User=$user_id, Email={$data['email']}", 'INFO');
    
    // Return appropriate response
    switch ($response_type) {
        case 'enrolled':
            // Redirect to payment selection
            echo 'enrolled_payment_required';
            break;
        case 'incompany_created':
            echo 'incompany_success';
            break;
        case 'interest_created':
            echo 'interest_success';
            break;
        case 'waitlist_created':
            echo 'waitlist_success';
            break;
        case 'already_enrolled':
            echo 'already_enrolled';
            break;
        case 'duplicate_interest':
            echo 'duplicate_interest';
            break;
        default:
            echo 'ok';
    }
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Registration error: " . $e->getMessage());
    logActivity("Registration error: " . $e->getMessage(), 'ERROR');
    echo 'error: ' . $e->getMessage();
}

// Enhanced email templates (would be in separate file in production)
function getEmailTemplate($type, $data) {
    $templates = [
        'enrollment' => [
            'subject' => "Inschrijving bevestigd: {$data['training_name']}",
            'body' => "
                Beste {$data['naam']},
                
                Je inschrijving voor {$data['training_name']} is ontvangen!
                
                Volgende stappen:
                1. Je wordt nu doorgeleid naar de betaalpagina
                2. Na betaling is je plaats definitief gereserveerd
                3. Een week voor de training ontvang je alle praktische informatie
                
                Vragen? Reageer gerust op deze e-mail.
                
                Met vriendelijke groet,
                Martijn Planken
                Inventijn
            "
        ],
        'interest' => [
            'subject' => "Interesse geregistreerd: {$data['training_name']}",
            'body' => "
                Beste {$data['naam']},
                
                Bedankt voor je interesse in {$data['training_name']}!
                
                Zodra we nieuwe data plannen, ben je de eerste die het hoort.
                
                Met vriendelijke groet,
                Martijn Planken
                Inventijn
            "
        ],
        'incompany' => [
            'subject' => "Incompany aanvraag ontvangen: {$data['training_name']}",
            'body' => "
                Beste {$data['naam']},
                
                Je aanvraag voor incompany training is ontvangen!
                
                Ik neem binnen 2 werkdagen contact met je op om de mogelijkheden te bespreken.
                
                Met vriendelijke groet,
                Martijn Planken
                Inventijn
            "
        ],
        'waitlist' => [
            'subject' => "Wachtlijst: {$data['training_name']}",
            'body' => "
                Beste {$data['naam']},
                
                De training is helaas vol, maar je bent toegevoegd aan de wachtlijst.
                
                Bij een annulering of nieuwe data ben je de eerste die het hoort.
                
                Met vriendelijke groet,
                Martijn Planken
                Inventijn
            "
        ]
    ];
    
    return $templates[$type] ?? null;
}

// Create necessary tables if they don't exist
function createTablesIfNeeded($pdo) {
    try {
        // Interests table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS interests (
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
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_training_type (training_type),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            )
        ");
        
        // Incompany requests table  
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS incompany_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                naam VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                telefoon VARCHAR(50),
                organisatie VARCHAR(255),
                training_type VARCHAR(100),
                training_name VARCHAR(255),
                aantal_deelnemers INT,
                gewenste_periode VARCHAR(255),
                gewenste_locatie VARCHAR(100),
                budget_indicatie VARCHAR(100),
                opmerkingen TEXT,
                status VARCHAR(50) DEFAULT 'new',
                source VARCHAR(100) DEFAULT 'website',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            )
        ");
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to create tables: " . $e->getMessage());
        logActivity("Failed to create tables: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Initialize tables on first run
createTablesIfNeeded($pdo);
?>
