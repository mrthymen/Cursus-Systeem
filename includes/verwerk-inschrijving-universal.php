<?php
/**
 * Debug Processor - Find Email Integration Issue v6.3.2
 * This version will help us identify exactly what's going wrong
 */

// Enable error reporting and clean output
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Clean any existing output
if (ob_get_level()) {
    ob_clean();
}

// Debug logging function
function debugLog($message, $step = '') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] DEBUG STEP $step: $message");
}

debugLog("Processor started", "START");

session_start();
debugLog("Session started", "1");

require_once 'config.php';
debugLog("Config loaded", "2");

// Email Configuration Constants - SAFE FALLBACKS
if (!defined('SITE_NAME')) define('SITE_NAME', 'Inventijn');
if (!defined('SITE_URL')) define('SITE_URL', 'https://inventijn.nl');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'martijn@inventijn.nl');
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'martijn@inventijn.nl'); // Use your email as fallback
if (!defined('FROM_NAME')) define('FROM_NAME', 'Inventijn Training');

debugLog("Email constants defined", "3");

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: text/plain; charset=utf-8');

debugLog("Headers set", "4");

// Security: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Invalid method: " . $_SERVER['REQUEST_METHOD'], "ERROR");
    http_response_code(405);
    echo 'method_not_allowed';
    exit;
}

debugLog("POST method confirmed", "5");

// Honeypot spam protection
if (!empty($_POST['website'])) {
    debugLog("Spam detected", "SECURITY");
    echo 'spam_detected';
    exit;
}

debugLog("Spam check passed", "6");

// Get database connection
try {
    debugLog("Attempting database connection", "7");
    $pdo = getDatabase();
    debugLog("Database connected successfully", "8");
} catch (Exception $e) {
    debugLog("Database connection failed: " . $e->getMessage(), "ERROR");
    echo 'database_error';
    exit;
}

// Validate required fields
$required_fields = ['training_type', 'training_name', 'naam', 'email'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        debugLog("Missing field: $field", "VALIDATION");
        echo "missing_field_$field";
        exit;
    }
}

debugLog("Required fields validated", "9");

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

debugLog("Data sanitized", "10");

// Validate email
if (!$data['email']) {
    debugLog("Invalid email", "VALIDATION");
    echo 'invalid_email';
    exit;
}

// Validate name
if (strlen($data['naam']) < 2) {
    debugLog("Invalid name", "VALIDATION");
    echo 'invalid_name';
    exit;
}

debugLog("Email and name validated", "11");

// ===== SIMPLIFIED EMAIL FUNCTIONS (with error handling) =====

/**
 * Simple Email Sending Function with Debug
 */
function sendSimpleEmail($to_email, $to_name, $subject, $message) {
    try {
        debugLog("Attempting to send email to: $to_email", "EMAIL");
        
        // Simple headers
        $headers = array();
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . ADMIN_EMAIL;
        
        // Send email
        $success = mail($to_email, $subject, $message, implode("\r\n", $headers));
        
        debugLog("Email send result: " . ($success ? 'SUCCESS' : 'FAILED'), "EMAIL");
        return $success;
        
    } catch (Exception $e) {
        debugLog("Email error: " . $e->getMessage(), "EMAIL_ERROR");
        return false;
    }
}

/**
 * Simple Email Templates
 */
function getSimpleEmailContent($type, $data) {
    $naam = htmlspecialchars($data['naam']);
    $training = htmlspecialchars($data['training_name']);
    
    switch ($type) {
        case 'enrollment':
            return [
                'subject' => "Inschrijving bevestigd: $training",
                'participant' => "
                    <h2>Inschrijving Bevestigd!</h2>
                    <p>Beste $naam,</p>
                    <p>Je inschrijving voor <strong>$training</strong> is ontvangen!</p>
                    <p>Volgende stappen: betaling → bevestiging → praktische info.</p>
                    <p>Met vriendelijke groet,<br>Martijn Planken<br>Inventijn</p>
                ",
                'admin' => "
                    <h2>Nieuwe Inschrijving</h2>
                    <p><strong>Training:</strong> $training</p>
                    <p><strong>Naam:</strong> $naam</p>
                    <p><strong>Email:</strong> {$data['email']}</p>
                    <p><strong>Telefoon:</strong> {$data['telefoon']}</p>
                    <p><strong>Organisatie:</strong> {$data['organisatie']}</p>
                "
            ];
            
        case 'interest':
            return [
                'subject' => "Interesse geregistreerd: $training",
                'participant' => "
                    <h2>Interesse Geregistreerd</h2>
                    <p>Beste $naam,</p>
                    <p>Bedankt voor je interesse in <strong>$training</strong>!</p>
                    <p>We houden je op de hoogte van nieuwe data.</p>
                    <p>Met vriendelijke groet,<br>Martijn Planken<br>Inventijn</p>
                ",
                'admin' => "
                    <h2>Nieuwe Interesse</h2>
                    <p><strong>Training:</strong> $training</p>
                    <p><strong>Naam:</strong> $naam</p>
                    <p><strong>Email:</strong> {$data['email']}</p>
                "
            ];
            
        case 'incompany':
            return [
                'subject' => "Incompany aanvraag: $training",
                'participant' => "
                    <h2>Incompany Aanvraag Ontvangen</h2>
                    <p>Beste $naam,</p>
                    <p>Je aanvraag voor <strong>$training</strong> is ontvangen!</p>
                    <p>Ik neem binnen 2 werkdagen contact op.</p>
                    <p>Met vriendelijke groet,<br>Martijn Planken<br>Inventijn</p>
                ",
                'admin' => "
                    <h2>Nieuwe Incompany Aanvraag</h2>
                    <p><strong>Training:</strong> $training</p>
                    <p><strong>Naam:</strong> $naam</p>
                    <p><strong>Email:</strong> {$data['email']}</p>
                    <p><strong>Deelnemers:</strong> {$data['aantal_deelnemers']}</p>
                "
            ];
            
        default:
            return [
                'subject' => "Aanmelding: $training",
                'participant' => "<p>Beste $naam,<br>Bedankt voor je aanmelding!</p>",
                'admin' => "<p>Nieuwe aanmelding van $naam voor $training</p>"
            ];
    }
}

/**
 * Send Confirmation Emails (Simplified)
 */
function sendConfirmationEmail($type, $data, $result_id = null) {
    try {
        debugLog("Starting email send for type: $type", "EMAIL_START");
        
        $email_content = getSimpleEmailContent($type, $data);
        
        if (!$email_content) {
            debugLog("No email template for type: $type", "EMAIL_ERROR");
            return false;
        }
        
        // Send to participant
        $participant_sent = sendSimpleEmail(
            $data['email'],
            $data['naam'],
            $email_content['subject'],
            $email_content['participant']
        );
        
        // Send to admin
        $admin_sent = sendSimpleEmail(
            ADMIN_EMAIL,
            'Martijn Planken',
            'Admin: ' . $email_content['subject'],
            $email_content['admin']
        );
        
        debugLog("Email results - Participant: " . ($participant_sent ? 'OK' : 'FAIL') . 
                ", Admin: " . ($admin_sent ? 'OK' : 'FAIL'), "EMAIL_RESULT");
        
        return $participant_sent;
        
    } catch (Exception $e) {
        debugLog("Email function error: " . $e->getMessage(), "EMAIL_ERROR");
        return false;
    }
}

// ===== DATABASE FUNCTIONS (existing, working) =====

function createOrGetUser($pdo, $naam, $email, $telefoon = '', $organisatie = '') {
    try {
        debugLog("Creating/getting user: $email", "USER");
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            // Update existing user info
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    name = ?, phone = ?, company = ?, updated_at = NOW()
                WHERE email = ?
            ");
            $stmt->execute([$naam, $telefoon, $organisatie, $email]);
            
            debugLog("User updated: " . $existing_user['id'], "USER");
            return $existing_user['id'];
        } else {
            // Create new user
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, company, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$naam, $email, $telefoon, $organisatie]);
            
            $user_id = $pdo->lastInsertId();
            debugLog("User created: $user_id", "USER");
            return $user_id;
        }
    } catch (Exception $e) {
        debugLog("User creation error: " . $e->getMessage(), "USER_ERROR");
        return false;
    }
}

function enrollUserInCourse($pdo, $user_id, $course_id) {
    try {
        debugLog("Enrolling user $user_id in course $course_id", "ENROLL");
        
        // Check if already enrolled
        $stmt = $pdo->prepare("
            SELECT id FROM course_participants 
            WHERE user_id = ? AND course_id = ? AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) {
            debugLog("User already enrolled", "ENROLL");
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
            debugLog("Course not found: $course_id", "ENROLL");
            return 'course_not_found';
        }
        
        if ($course_info['current_participants'] >= $course_info['max_participants']) {
            debugLog("Course full", "ENROLL");
            return 'course_full';
        }
        
        // Enroll user
        $stmt = $pdo->prepare("
            INSERT INTO course_participants 
            (user_id, course_id, enrollment_date, payment_status)
            VALUES (?, ?, NOW(), 'pending')
        ");
        $stmt->execute([$user_id, $course_id]);
        
        $enrollment_id = $pdo->lastInsertId();
        debugLog("Enrollment successful: $enrollment_id", "ENROLL");
        return $enrollment_id;
        
    } catch (Exception $e) {
        debugLog("Enrollment error: " . $e->getMessage(), "ENROLL_ERROR");
        return false;
    }
}

function createInterest($pdo, $data) {
    try {
        debugLog("Creating interest for: " . $data['email'], "INTEREST");
        
        $periode_text = is_array($data['periode']) ? implode(', ', $data['periode']) : '';
        
        // Check for existing interest (duplicate prevention)
        $stmt = $pdo->prepare("
            SELECT id FROM interests 
            WHERE email = ? AND training_name = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$data['email'], $data['training_name']]);
        
        if ($stmt->fetch()) {
            debugLog("Duplicate interest detected", "INTEREST");
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
        debugLog("Interest created: $interest_id", "INTEREST");
        return $interest_id;
        
    } catch (Exception $e) {
        debugLog("Interest creation error: " . $e->getMessage(), "INTEREST_ERROR");
        return false;
    }
}

function createIncompanyRequest($pdo, $data) {
    try {
        debugLog("Creating incompany request for: " . $data['email'], "INCOMPANY");
        
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
        debugLog("Incompany request created: $request_id", "INCOMPANY");
        return $request_id;
        
    } catch (Exception $e) {
        debugLog("Incompany creation error: " . $e->getMessage(), "INCOMPANY_ERROR");
        return false;
    }
}

// ===== MAIN PROCESSING LOGIC =====

try {
    debugLog("Starting main processing", "MAIN");
    $pdo->beginTransaction();
    
    // Create or get user
    debugLog("Creating user", "MAIN_USER");
    $user_id = createOrGetUser($pdo, $data['naam'], $data['email'], $data['telefoon'], $data['organisatie']);
    if (!$user_id) {
        throw new Exception("Failed to create/update user");
    }
    
    debugLog("User ID: $user_id", "MAIN");
    
    $result = null;
    $response_type = null;
    
    // Process based on registration type and course selection
    if ($data['training_type'] === 'incompany') {
        debugLog("Processing incompany", "MAIN_INCOMPANY");
        // Incompany request
        $result = createIncompanyRequest($pdo, $data);
        if ($result) {
            debugLog("Sending incompany email", "MAIN_EMAIL");
            sendConfirmationEmail('incompany', $data, $result);
            $response_type = 'incompany_created';
        } else {
            throw new Exception("Failed to create incompany request");
        }
        
    } else if (!empty($data['selected_course_id']) && $data['selected_course_id'] !== 'other' && is_numeric($data['selected_course_id'])) {
        debugLog("Processing enrollment for course: " . $data['selected_course_id'], "MAIN_ENROLL");
        // Direct course enrollment
        $enrollment_result = enrollUserInCourse($pdo, $user_id, $data['selected_course_id']);
        
        if (is_numeric($enrollment_result)) {
            debugLog("Sending enrollment email", "MAIN_EMAIL");
            // Successfully enrolled - send email and prepare for payment
            sendConfirmationEmail('enrollment', $data, $enrollment_result);
            
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
            
        } else if ($enrollment_result === 'already_enrolled') {
            $response_type = 'already_enrolled';
        } else if ($enrollment_result === 'course_full') {
            debugLog("Course full, creating interest", "MAIN_WAITLIST");
            // Course is full, create interest instead
            $interest_result = createInterest($pdo, $data);
            if ($interest_result && $interest_result !== 'duplicate_interest') {
                debugLog("Sending waitlist email", "MAIN_EMAIL");
                sendConfirmationEmail('interest', $data, $interest_result); // Use 'interest' template for waitlist
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
        debugLog("Processing interest registration", "MAIN_INTEREST");
        // Interest registration (other moment or no specific course)
        $result = createInterest($pdo, $data);
        if ($result && $result !== 'duplicate_interest') {
            debugLog("Sending interest email", "MAIN_EMAIL");
            sendConfirmationEmail('interest', $data, $result);
            $response_type = 'interest_created';
        } else if ($result === 'duplicate_interest') {
            $response_type = 'duplicate_interest';
        } else {
            throw new Exception("Failed to create interest");
        }
    }
    
    debugLog("Committing transaction", "MAIN_COMMIT");
    $pdo->commit();
    
    debugLog("Final response type: $response_type", "MAIN_RESPONSE");
    
    // Return appropriate response
    switch ($response_type) {
        case 'enrolled':
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
    
    debugLog("Response sent successfully", "END");
    
} catch (Exception $e) {
    debugLog("Exception: " . $e->getMessage(), "ERROR");
    $pdo->rollback();
    echo 'error: ' . $e->getMessage();
}

debugLog("Script execution completed", "FINAL");
?>