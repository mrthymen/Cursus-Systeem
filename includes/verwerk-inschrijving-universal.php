<?php
/**
 * Fixed Registration Processor - v6.2.3
 * FIXED: Column name mapping to match existing database structure
 * telefoon → phone, organisatie → company
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
    exit('method_not_allowed');
}

// Honeypot spam protection
if (!empty($_POST['website'])) {
    exit('spam_detected');
}

// Get database connection
try {
    $pdo = getDatabase();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit('database_error');
}

// Validate required fields
$required_fields = ['training_type', 'training_name', 'naam', 'email'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
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
    exit('invalid_email');
}

// Validate name
if (strlen($data['naam']) < 2) {
    exit('invalid_name');
}

// FIXED: Helper function with correct column names
function createOrGetUser($pdo, $naam, $email, $telefoon = '', $organisatie = '') {
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            // Update existing user info - FIXED: Use correct column names
            $stmt = $pdo->prepare("
                UPDATE users SET 
                    name = ?, phone = ?, company = ?, updated_at = NOW()
                WHERE email = ?
            ");
            $stmt->execute([$naam, $telefoon, $organisatie, $email]);
            
            return $existing_user['id'];
        } else {
            // Create new user - FIXED: Use correct column names (phone, company)
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, company, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$naam, $email, $telefoon, $organisatie]);
            
            $user_id = $pdo->lastInsertId();
            return $user_id;
        }
    } catch (Exception $e) {
        error_log("Error creating/updating user: " . $e->getMessage());
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
            return 'course_not_found';
        }
        
        if ($course_info['current_participants'] >= $course_info['max_participants']) {
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
        return $enrollment_id;
        
    } catch (Exception $e) {
        error_log("Error enrolling user: " . $e->getMessage());
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
        return $interest_id;
        
    } catch (Exception $e) {
        error_log("Error creating interest: " . $e->getMessage());
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
        return $request_id;
        
    } catch (Exception $e) {
        error_log("Error creating incompany request: " . $e->getMessage());
        return false;
    }
}

// Main processing logic
try {
    $pdo->beginTransaction();
    
    // Create or get user - FIXED: Pass correct parameters
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
            $response_type = 'incompany_created';
        } else {
            throw new Exception("Failed to create incompany request");
        }
        
    } else if (!empty($data['selected_course_id']) && $data['selected_course_id'] !== 'other' && is_numeric($data['selected_course_id'])) {
        // Direct course enrollment
        $enrollment_result = enrollUserInCourse($pdo, $user_id, $data['selected_course_id']);
        
        if (is_numeric($enrollment_result)) {
            // Successfully enrolled - prepare for payment
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
            // Course is full, create interest instead
            $interest_result = createInterest($pdo, $data);
            if ($interest_result && $interest_result !== 'duplicate_interest') {
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
            $response_type = 'interest_created';
        } else if ($result === 'duplicate_interest') {
            $response_type = 'duplicate_interest';
        } else {
            throw new Exception("Failed to create interest");
        }
    }
    
    $pdo->commit();
    
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
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Registration error: " . $e->getMessage());
    echo 'error: ' . $e->getMessage();
}
?>