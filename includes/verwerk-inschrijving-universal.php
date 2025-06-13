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
/**
 * Simple Email Content Generator v6.3.4 - Inventijn Branded Templates
 * Updated: 2025-06-13 - Martijn's nieuwe Inventijn huisstijl
 */
function getSimpleEmailContent($type, $data) {
    debugLog("EMAIL: Generating content for type: $type", "EMAIL_CONTENT");
    
    $naam = htmlspecialchars($data['naam']);
    $training = htmlspecialchars($data['training_name']);
    $email = htmlspecialchars($data['email']);
    $organisatie = htmlspecialchars($data['organisatie'] ?? '');
    
   // CORRECT - haal course_data uit data array:
$course_data = $data['course_data'] ?? [];
$inventijn_template = getInventijnEmailTemplate($naam, $training, $type, $course_data);

    $templates = [
        'enrollment' => [
            'subject' => "🎉 Je bent ingeschreven bij $training - Inventijn",
            'participant' => $inventijn_template,
            'admin' => "<h2>✅ Nieuwe Inschrijving</h2><p><strong>Training:</strong> $training</p><p><strong>Naam:</strong> $naam</p><p><strong>Email:</strong> $email</p><p><strong>Organisatie:</strong> " . ($organisatie ?: 'Niet opgegeven') . "</p><p><strong>Type:</strong> Directe inschrijving</p>"
        ],
        'interest' => [
            'subject' => "🎯 Interesse geregistreerd voor $training - Inventijn", 
            'participant' => $inventijn_template,
            'admin' => "<h2>🎯 Nieuwe Interesse</h2><p><strong>Training:</strong> $training</p><p><strong>Naam:</strong> $naam</p><p><strong>Email:</strong> $email</p><p><strong>Organisatie:</strong> " . ($organisatie ?: 'Niet opgegeven') . "</p><p><strong>Type:</strong> Interesse registratie</p>"
        ],
        'incompany' => [
            'subject' => "🏢 Incompany aanvraag voor $training - Inventijn",
            'participant' => $inventijn_template,
            'admin' => "<h2>🏢 Incompany Aanvraag</h2><p><strong>Training:</strong> $training</p><p><strong>Naam:</strong> $naam</p><p><strong>Email:</strong> $email</p><p><strong>Organisatie:</strong> " . ($organisatie ?: 'Niet opgegeven') . "</p><p><strong>Type:</strong> Incompany verzoek</p>"
        ]
    ];
    
    debugLog("EMAIL: Template found for type: $type", "EMAIL_CONTENT");
    return $templates[$type] ?? $templates['interest'];
}

/**
 * Get full Inventijn branded email template v6.3.6
 * NOW WITH REAL COURSE DATA FOR ENROLLMENTS
 */
function getInventijnEmailTemplate($naam, $training, $type = 'interest', $course_data = []) {
    // Determine content based on type
    $header_emoji = ($type === 'enrollment') ? '🎉' : (($type === 'incompany') ? '🏢' : '🎯');
    $header_text = ($type === 'enrollment') ? 'Je bent ingeschreven!' : (($type === 'incompany') ? 'Incompany aanvraag ontvangen!' : 'Interesse geregistreerd!');
    
    // Dynamic welcome text
    if ($type === 'enrollment') {
        $welcome_text = "Wat fijn dat ik je mag verwelkomen bij <strong>$training</strong>. Ik hoop dat we er samen een mooie cursus van kunnen maken.";
    } elseif ($type === 'incompany') {
        $welcome_text = "Wat fijn dat je interesse hebt in een incompany training voor <strong>$training</strong>. Ik neem binnenkort contact met je op om de mogelijkheden te bespreken.";
    } else {
        $welcome_text = "Wat fijn dat je interesse hebt getoond in <strong>$training</strong>. Zodra er nieuwe data beschikbaar zijn, laat ik het je weten!";
    }
    
    // Course details section for enrollments
    $course_details_html = '';
    if ($type === 'enrollment' && !empty($course_data)) {
        $course_details_html = '
        <div style="background: white; border-radius: 12px; padding: 24px; margin: 20px 0; box-shadow: 0 2px 8px rgba(62, 92, 198, 0.1);">
            <div style="display: flex; align-items: center; margin: 12px 0; padding: 8px 0; border-bottom: 1px solid rgba(227, 161, 229, 0.3);">
                <span style="width: 24px; margin-right: 12px; font-size: 18px;">📅</span>
                <span style="font-weight: 500; color: #b998e4; min-width: 80px;">Datum:</span>
                <span style="color: #1E293B; font-weight: 600;">' . ($course_data['course_date'] ?? 'Wordt nog bekendgemaakt') . '</span>
            </div>
            <div style="display: flex; align-items: center; margin: 12px 0; padding: 8px 0; border-bottom: 1px solid rgba(227, 161, 229, 0.3);">
                <span style="width: 24px; margin-right: 12px; font-size: 18px;">🕒</span>
                <span style="font-weight: 500; color: #b998e4; min-width: 80px;">Tijd:</span>
                <span style="color: #1E293B; font-weight: 600;">' . ($course_data['course_time'] ?? 'Wordt nog bekendgemaakt') . '</span>
            </div>
            <div style="display: flex; align-items: center; margin: 12px 0; padding: 8px 0;">
                <span style="width: 24px; margin-right: 12px; font-size: 18px;">📍</span>
                <span style="font-weight: 500; color: #b998e4; min-width: 80px;">Locatie:</span>
                <span style="color: #1E293B; font-weight: 600;">' . ($course_data['course_location'] ?? 'Wordt nog bekendgemaakt') . '</span>
            </div>
        </div>';
    }
    
    // Access section for enrollments
    $access_section_html = '';
    if ($type === 'enrollment' && !empty($course_data['access_key'])) {
        $access_section_html = '
        <div style="background: linear-gradient(135deg, #F9CB40, #F9A03F); border-radius: 16px; padding: 32px; margin: 32px 0; text-align: center;">
            <span style="font-size: 48px; margin-bottom: 16px; display: block;">🔑</span>
            <h3 style="color: #1E293B; font-size: 20px; margin: 0 0 12px 0;">Jouw Persoonlijke Toegangscode</h3>
            <div style="background: white; color: #3e5cc6; font-size: 24px; font-weight: 700; padding: 16px 24px; border-radius: 12px; margin: 20px 0; letter-spacing: 2px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                ' . $course_data['access_key'] . '
            </div>
            <p style="color: #1E293B; font-size: 14px; margin: 16px 0 0 0; opacity: 0.8;">
                Bewaar deze code goed - je hebt hem nodig voor toegang tot je cursusmateriaal
            </p>
        </div>';
    }
    
    // CTA section 
    $cta_text = ($type === 'enrollment') ? '🚀 Ga naar Cursusmateriaal' : '📧 Neem Contact Op';
    $cta_url = ($type === 'enrollment' && !empty($course_data['login_url'])) ? $course_data['login_url'] : 'mailto:martijn@inventijn.nl';
    
    $cta_note = '';
    if ($type === 'enrollment' && !empty($course_data['access_start_time'])) {
        $cta_note = '<p style="margin: 16px 0; color: #64748B; font-size: 14px;">Materiaal wordt beschikbaar vanaf: <strong>' . $course_data['access_start_time'] . '</strong></p>';
    }
    
    return '<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $header_text . ' - Inventijn</title>
    <!-- CSS same as before -->
    <style>
        :root {
            --inventijn-light-pink: #e3a1e5;
            --inventijn-purple: #b998e4;
            --inventijn-light-blue: #6b80e8;
            --inventijn-dark-blue: #3e5cc6;
            --inventijn-yellow: #F9CB40;
            --inventijn-orange: #F9A03F;
            --white: #FFFFFF;
            --grey-light: #F8FAFC;
            --grey-medium: #64748B;
            --grey-dark: #1E293B;
        }
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        .font-display { font-family: "Space Grotesk", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-weight: 600; line-height: 1.2; }
        .font-body { font-family: "Barlow", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-weight: 400; line-height: 1.6; }
        .email-container { max-width: 600px; margin: 0 auto; background: var(--white); font-family: "Barlow", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        .header-gradient { background: linear-gradient(135deg, var(--inventijn-dark-blue) 0%, var(--inventijn-light-blue) 50%, var(--inventijn-purple) 100%); padding: 40px 32px; text-align: center; position: relative; overflow: hidden; }
        .logo-container { margin-bottom: 24px; }
        .header-title { color: var(--white); font-size: 28px; margin: 0 0 8px 0; }
        .header-subtitle { color: rgba(255,255,255,0.9); font-size: 16px; margin: 0; }
        .welcome-section { padding: 40px 32px 32px; background: var(--white); }
        .welcome-title { color: var(--inventijn-dark-blue); font-size: 24px; margin: 0 0 16px 0; text-align: center; }
        .welcome-text { color: var(--grey-dark); font-size: 16px; margin: 0 0 24px 0; text-align: center; }
        .course-card { background: linear-gradient(135deg, var(--grey-light) 0%, var(--white) 100%); border: 2px solid var(--inventijn-light-blue); border-radius: 16px; padding: 32px; margin: 32px 0; position: relative; overflow: hidden; }
        .course-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--inventijn-dark-blue), var(--inventijn-purple), var(--inventijn-light-blue)); }
        .course-title { color: var(--inventijn-dark-blue); font-size: 22px; margin: 0 0 20px 0; text-align: center; }
        .cta-container { text-align: center; margin: 40px 0; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, var(--inventijn-dark-blue), var(--inventijn-light-blue)); color: var(--white) !important; text-decoration: none; padding: 18px 36px; border-radius: 50px; font-size: 16px; font-weight: 600; box-shadow: 0 4px 16px rgba(62, 92, 198, 0.3); }
        .footer { background: var(--grey-light); padding: 40px 32px; text-align: center; border-top: 1px solid rgba(227, 161, 229, 0.3); }
        .footer-contact { color: var(--grey-medium); font-size: 14px; margin: 8px 0; }
        .footer-legal { color: var(--grey-medium); font-size: 12px; margin-top: 24px; padding-top: 24px; border-top: 1px solid rgba(100, 116, 139, 0.2); }
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .header-gradient { padding: 32px 20px; }
            .header-title { font-size: 24px; }
            .welcome-section, .course-card, .footer { padding: 24px 20px; }
            .course-title { font-size: 20px; }
            .cta-button { padding: 16px 32px; font-size: 15px; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc;">
    <div class="email-container">
        <div class="header-gradient">
            <div class="logo-container">
                <img src="https://inventijn.nl/assets/images/logo-pink.svg" alt="Inventijn Logo" style="height: 48px; width: auto;">
            </div>
            <h1 class="header-title font-display">' . $header_emoji . ' ' . $header_text . '</h1>
            <p class="header-subtitle font-body">Welkom bij de volgende stap in je ontwikkeling</p>
        </div>

        <div class="welcome-section">
            <h2 class="welcome-title font-display">Hallo ' . $naam . '!</h2>
            <p class="welcome-text font-body">' . $welcome_text . '</p>
        </div>

        <div class="course-card">
            <h3 class="course-title font-display">📚 ' . $training . '</h3>
            ' . $course_details_html . '
            ' . ($type !== 'enrollment' ? '<p style="text-align: center; color: var(--grey-medium); margin: 0;">Ik neem binnenkort contact met je op voor de volgende stappen.</p>' : '') . '
        </div>

        ' . $access_section_html . '

        <div class="cta-container">
            <a href="' . $cta_url . '" class="cta-button font-medium">' . $cta_text . '</a>
            ' . $cta_note . '
        </div>

        <div class="footer">
            <div style="margin-bottom: 20px;">
                <img src="https://inventijn.nl/assets/images/logo.svg" alt="Inventijn" style="height: 32px; width: auto;">
            </div>
            <p class="footer-contact font-body">
                <strong>Inventijn</strong><br>
                Dr. Hub van Doorneweg 195, 5026 RE Tilburg<br>
                📧 martijn@inventijn.nl
            </p>
            <p class="footer-legal font-body">© 2025 Inventijn. Alle rechten voorbehouden.</p>
        </div>
    </div>
</body>
</html>';
}

/**
 * Main Email Sending Function
 */
$data['course_data'] = $course_data;

// Send confirmation email
debugLog("MAIN: Calling sendConfirmationEmail with course_data...", "MAIN_EMAIL");
$email_sent = sendConfirmationEmail($email_type, $data, $result);
 
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
        // First check if user exists
        $stmt = $pdo->prepare("SELECT id, access_key FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_user) {
            debugLog("DB: User exists, updating - ID: " . $existing_user['id'], "DB_USER");
            
            // Ensure access_key exists
            if (empty($existing_user['access_key'])) {
                $access_key = generateSecureAccessKey();
                $stmt = $pdo->prepare("UPDATE users SET access_key = ? WHERE id = ?");
                $stmt->execute([$access_key, $existing_user['id']]);
                debugLog("DB: Generated missing access_key for existing user", "DB_USER");
            }
            
            // Try to update with both possible column name variations
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, company = ?, updated_at = NOW() WHERE email = ?");
                $stmt->execute([$naam, $telefoon, $organisatie, $email]);
            } catch (PDOException $e) {
                debugLog("DB: Failed with phone/company, trying telefoon/organisatie", "DB_USER");
                try {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, telefoon = ?, organisatie = ?, updated_at = NOW() WHERE email = ?");
                    $stmt->execute([$naam, $telefoon, $organisatie, $email]);
                } catch (PDOException $e2) {
                    debugLog("DB: Both update attempts failed, continuing with basic user", "DB_USER");
                }
            }
            
            return $existing_user['id'];
            
        } else {
            debugLog("DB: Creating new user", "DB_USER");
            
            // Generate unique access key
            $access_key = generateSecureAccessKey();
            debugLog("DB: Generated access_key: $access_key", "DB_USER");
            
            // Try to create user with different column variations
            try {
                // First try with phone/company columns
                $stmt = $pdo->prepare("INSERT INTO users (name, email, access_key, phone, company, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$naam, $email, $access_key, $telefoon, $organisatie]);
            } catch (PDOException $e) {
                debugLog("DB: Failed with phone/company, trying telefoon/organisatie", "DB_USER");
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, access_key, telefoon, organisatie, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
                    $stmt->execute([$naam, $email, $access_key, $telefoon, $organisatie]);
                } catch (PDOException $e2) {
                    debugLog("DB: Both insert attempts failed, trying minimal", "DB_USER");
                    // Ultra-minimal insert
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, access_key, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$naam, $email, $access_key]);
                }
            }
            
            $user_id = $pdo->lastInsertId();
            debugLog("DB: New user created - ID: $user_id with access_key: $access_key", "DB_USER");
            return $user_id;
        }
        
    } catch (PDOException $e) {
        debugLog("DB ERROR: User creation failed - " . $e->getMessage(), "DB_ERROR");
        return false;
    }
}

/**
 * Generate secure access key if function doesn't exist yet
 */
if (!function_exists('generateSecureAccessKey')) {
    function generateSecureAccessKey() {
        return bin2hex(random_bytes(16)); // 32 character hex string
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
// ===== IMPROVED EMAIL TYPE DETECTION =====

// Determine email type based on actual registration action
debugLog("MAIN: Determining email type - selected_course_id: " . ($data['selected_course_id'] ?? 'none'), "MAIN_EMAIL_TYPE");

if ($data['training_type'] === 'incompany') {
    $email_type = 'incompany';
    debugLog("MAIN: Email type set to incompany", "MAIN_EMAIL_TYPE");
} elseif (!empty($data['selected_course_id']) && $data['selected_course_id'] !== 'other') {
    // User selected a specific course = direct enrollment
    $email_type = 'enrollment';
    debugLog("MAIN: Email type set to enrollment (direct course selection)", "MAIN_EMAIL_TYPE");
} else {
    // No specific course selected = interest registration
    $email_type = 'interest';
    debugLog("MAIN: Email type set to interest (no specific course)", "MAIN_EMAIL_TYPE");
}

debugLog("MAIN: Final email type determined: $email_type", "MAIN_EMAIL_TYPE");
    
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