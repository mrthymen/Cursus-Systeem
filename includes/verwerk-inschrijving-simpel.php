<?php
/**
 * Inventijn Form Processing - Compatible met config v3.0.0
 * 
 * @version 3.1.0
 * @author Inventijn Development Team
 * @date 2025-06-07
 * @compatible-with config.php v3.0.0
 */

require_once 'config.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: text/plain; charset=utf-8');

// Alleen POST requests toestaan
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logActivity("Invalid request method: " . $_SERVER['REQUEST_METHOD'], 'SECURITY');
    die('Method not allowed');
}

// Honeypot spam check
if (!empty($_POST['website'])) {
    logActivity("Spam attempt detected from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'SECURITY');
    die('spam');
}

// Rate limiting check
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($client_ip)) {
    logActivity("Rate limit exceeded for IP: $client_ip", 'SECURITY');
    die('Te snel! Wacht ' . FORM_RATE_LIMIT_SECONDS . ' seconden tussen aanmeldingen.');
}

try {
    // Database connectie via config
    $pdo = getDatabase();
    
    // Input validatie en sanitatie
    $naam = sanitizeInput($_POST['naam'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $organisatie = sanitizeInput($_POST['organisatie'] ?? '');
    $training = sanitizeInput($_POST['training'] ?? '');
    $opmerkingen = sanitizeInput($_POST['opmerkingen'] ?? '');
    
    // Basis validatie
    if (empty($naam) || strlen($naam) < 2) {
        die('Naam is verplicht en moet minimaal 2 letters bevatten');
    }
    
    if (!validateEmail($email)) {
        die('Geldig e-mailadres is verplicht');
    }
    
    if (empty($training)) {
        die('Training type is verplicht');
    }
    
    // Bepaal training type
    $training_type = determineTrainingTypeFromName($training);
    
    // Process periode voorkeur
    $preferred_periods = [];
    if (isset($_POST['periode']) && is_array($_POST['periode'])) {
        $preferred_periods = array_filter(array_map('trim', $_POST['periode']));
    } elseif (!empty($_POST['gewenste_periode'])) {
        $preferred_periods = [trim($_POST['gewenste_periode'])];
    }
    
    // Voor incompany: aantal deelnemers
    $participant_count = 1;
    if ($training_type === 'incompany' && !empty($_POST['aantal_deelnemers'])) {
        $participant_count = max(1, intval($_POST['aantal_deelnemers']));
    }
    
    logActivity("Processing form submission: $training_type for $naam ($email)", 'INFO');
    
    // Begin database transactie
    $pdo->beginTransaction();
    
    // ==========================================
    // STAP 1: USER MANAGEMENT
    // ==========================================
    
    // Check of user al bestaat
    $stmt = $pdo->prepare("SELECT id, access_key, name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing_user = $stmt->fetch();
    
    $user_id = null;
    $access_key = null;
    $is_new_user = false;
    
    if ($existing_user) {
        // Update bestaande user
        $user_id = $existing_user['id'];
        $access_key = $existing_user['access_key'];
        
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, company = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$naam, $organisatie, $user_id]);
        
        logActivity("Updated existing user ID: $user_id", 'INFO');
        
    } else {
        // Maak nieuwe user aan
        $access_key = generateSecureAccessKey();
        $is_new_user = true;
        
        $stmt = $pdo->prepare("
            INSERT INTO users (email, name, access_key, company, notes, active, created_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $email,
            $naam, 
            $access_key,
            $organisatie,
            "Aangemeld via website formulier voor: {$training}"
        ]);
        
        $user_id = $pdo->lastInsertId();
        // Direct na: $user_id = $pdo->lastInsertId();
        error_log("🎯 USER CREATED: $user_id, TRAINING: $training");
        logActivity("Created new user ID: $user_id ($email)", 'INFO');
    }
    
    // ==========================================
    // STAP 2: INTEREST REGISTRATION
    // ==========================================
    
    // Check of deze interesse al bestaat (duplicaat preventie)
    $stmt = $pdo->prepare("
        SELECT id FROM course_interest 
        WHERE user_id = ? AND training_type = ? AND status = 'pending'
        AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)
    ");
    $stmt->execute([$user_id, $training_type, DUPLICATE_PREVENTION_HOURS]);
    $existing_interest = $stmt->fetch();
    
    $interest_id = null;
    
    if ($existing_interest) {
        // Update bestaande interesse
        $stmt = $pdo->prepare("
            UPDATE course_interest 
            SET preferred_periods = ?, availability_comment = ?, participant_count = ?, 
                company = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            json_encode($preferred_periods),
            $opmerkingen,
            $participant_count,
            $organisatie,
            $existing_interest['id']
        ]);
        
        $interest_id = $existing_interest['id'];
        
        logActivity("Updated existing interest ID: $interest_id", 'INFO');
        
    } else {
        // Maak nieuwe interest record aan
        $priority = ($training_type === 'incompany') ? 'high' : 'normal';
        
        $stmt = $pdo->prepare("
            INSERT INTO course_interest (
                user_id, training_type, training_name, preferred_periods,
                availability_comment, participant_count, company, status,
                priority, source, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'website', NOW())
        ");
        $stmt->execute([
            $user_id,
            $training_type,
            $training,
            json_encode($preferred_periods),
            $opmerkingen,
            $participant_count,
            $organisatie,
            $priority
        ]);
        
        $interest_id = $pdo->lastInsertId();
        
        logActivity("Created new interest ID: $interest_id ($training_type)", 'INFO');
    }
    
    // ==========================================
    // STAP 3: EMAIL AUTOMATION
    // ==========================================
    
    if (AUTO_EMAIL_NOTIFICATIONS) {
        // Login URL met access key
        $login_url = BASE_URL . 'login.php?access=' . urlencode($access_key);
        
        // Email naar gebruiker (bevestiging)
        if ($is_new_user) {
            queueEmailFromTemplate($pdo, 'user_welcome', [
                'recipient_email' => $email,
                'recipient_name' => $naam,
                'user_id' => $user_id,
                'priority' => 5,
                'variables' => [
                    'user_name' => $naam,
                    'training_name' => $training,
                    'access_key' => $access_key,
                    'login_url' => $login_url,
                    'periods' => implode(', ', $preferred_periods)
                ]
            ]);
        } else {
            queueEmailFromTemplate($pdo, 'interest_confirmation', [
                'recipient_email' => $email,
                'recipient_name' => $naam,
                'user_id' => $user_id,
                'priority' => 5,
                'variables' => [
                    'user_name' => $naam,
                    'training_name' => $training,
                    'periods' => implode(', ', $preferred_periods),
                    'login_url' => $login_url
                ]
            ]);
        }
        
        // Email naar admin (notificatie)
        $admin_template = ($training_type === 'incompany') ? 'admin_incompany_request' : 'admin_new_interest';
        $admin_priority = ($training_type === 'incompany') ? 1 : 3;
        
        queueEmailFromTemplate($pdo, $admin_template, [
            'recipient_email' => ADMIN_EMAIL,
            'recipient_name' => 'Admin',
            'priority' => $admin_priority,
            'variables' => [
                'user_name' => $naam,
                'user_email' => $email,
                'company' => $organisatie ?: 'Niet opgegeven',
                'training_name' => $training,
                'periods' => implode(', ', $preferred_periods),
                'participant_count' => $participant_count,
                'comments' => $opmerkingen ?: 'Geen opmerkingen',
                'admin_url' => BASE_URL . 'admin/planning.php',
                'interest_id' => $interest_id,
                'is_new_user' => $is_new_user ? 'Ja (nieuwe registratie)' : 'Nee (bestaande user)'
            ]
        ]);
        
        logActivity("Queued emails for interest ID: $interest_id", 'INFO');
    }
    
    // ==========================================
    // STAP 4: ACCESS LOGGING
    // ==========================================
    
    $stmt = $pdo->prepare("
        INSERT INTO access_log (user_id, action, resource, ip_address, user_agent, success, details, timestamp)
        VALUES (?, 'form_submission', ?, ?, ?, 1, ?, NOW())
    ");
    
    $details = json_encode([
        'training_type' => $training_type,
        'participant_count' => $participant_count,
        'is_new_user' => $is_new_user,
        'interest_id' => $interest_id
    ]);
    
    $stmt->execute([
        $user_id,
        'formulier-ai2.php',
        $client_ip,
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        $details
    ]);
    
    // ==========================================
    // STAP 5: FINALIZATION
    // ==========================================
    
    // Commit transaction
    $pdo->commit();
    
    logActivity("Form submission completed successfully for interest ID: $interest_id", 'INFO');
    
    // Success response
    echo 'ok';
    
} catch (PDOException $e) {
    if (isset($pdo)) $pdo->rollBack();
    
    $error_msg = "Database error in form processing: " . $e->getMessage();
    logActivity($error_msg, 'ERROR');
    
    http_response_code(500);
    die('Er is een probleem opgetreden. Probeer het later opnieuw.');
    
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    
    $error_msg = "General error in form processing: " . $e->getMessage();
    logActivity($error_msg, 'ERROR');
    
    http_response_code(500);
    die('Er is een onverwachte fout opgetreden.');
}

/**
 * ==========================================
 * UTILITY FUNCTIONS (compatible met config v3.0.0)
 * ==========================================
 */

/**
 * Legacy support functies (voor backwards compatibility)
 */
function determineTrainingType($training_name) {
    return determineTrainingTypeFromName($training_name);
}

function generateAccessKey() {
    return generateSecureAccessKey();
}

function queueEmail($pdo, $email_data) {
    return queueEmailFromTemplate($pdo, $email_data['template'], $email_data);
}

/**
 * Bepaal training type uit naam (compatible versie)
 */
function determineTrainingTypeFromName($training_name) {
    $name_lower = strtolower($training_name);
    
    if (strpos($name_lower, 'introductie') !== false) {
        return 'introductie';
    } elseif (strpos($name_lower, 'verdieping') !== false) {
        return 'verdieping';
    } elseif (strpos($name_lower, 'combi') !== false) {
        return 'combi';
    } elseif (strpos($name_lower, 'incompany') !== false) {
        return 'incompany';
    } else {
        return 'algemeen';
    }
}

/**
 * Queue email from template (compatible met config v3.0.0)
 */
function queueEmailFromTemplate($pdo, $template_key, $email_data) {
    try {
        // Haal template op via config functie
        $template = getEmailTemplate($pdo, $template_key);
        if (!$template) {
            throw new Exception("Email template '{$template_key}' not found");
        }
        
        // Replace variables via config functie
        $subject = replaceEmailVariables($template['subject'], $email_data['variables']);
        $body_html = replaceEmailVariables($template['body_html'], $email_data['variables']);
        $body_text = replaceEmailVariables($template['body_text'], $email_data['variables']);
        
        // Queue email
        $stmt = $pdo->prepare("
            INSERT INTO email_queue (
                recipient_email, recipient_name, subject, body_html, body_text,
                template_used, priority, status, user_id, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");
        
        $stmt->execute([
            $email_data['recipient_email'],
            $email_data['recipient_name'] ?? null,
            $subject,
            $body_html,
            $body_text,
            $template_key,
            $email_data['priority'] ?? 5,
            $email_data['user_id'] ?? null
        ]);
        
        return true;
        
    } catch (Exception $e) {
        logActivity("Failed to queue email template '$template_key': " . $e->getMessage(), 'ERROR');
        return false;
    }
}
?>