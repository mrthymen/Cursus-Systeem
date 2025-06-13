<?php
/**
 * Complete Registration Processor with Email System v6.3.1
 * ADDED: Full email system integration with professional templates
 * Features: Database enrollment + Email confirmations + Admin notifications
 */

session_start();
require_once 'config.php';

// Email Configuration Constants (add these to config.php)
if (!defined('SITE_NAME')) define('SITE_NAME', 'Inventijn');
if (!defined('SITE_URL')) define('SITE_URL', 'https://inventijn.nl');
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'martijn@inventijn.nl');
if (!defined('FROM_EMAIL')) define('FROM_EMAIL', 'noreply@inventijn.nl');
if (!defined('FROM_NAME')) define('FROM_NAME', 'Inventijn Training');

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

// ===== EMAIL SYSTEM FUNCTIONS =====

/**
 * Enhanced Email Sending Function
 */
function sendConfirmationEmail($type, $data, $result_id = null) {
    try {
        // Get email template and content
        $email_content = getEmailTemplate($type, $data, $result_id);
        
        if (!$email_content) {
            error_log("No email template found for type: $type");
            return false;
        }
        
        // Send to participant
        $participant_sent = sendEmail(
            $data['email'],
            $data['naam'],
            $email_content['subject'],
            $email_content['body_html'],
            $email_content['body_text']
        );
        
        // Send admin notification
        $admin_sent = sendAdminNotification($type, $data, $result_id);
        
        // Log results
        error_log("Email sent - Participant: " . ($participant_sent ? 'SUCCESS' : 'FAILED') . 
                 ", Admin: " . ($admin_sent ? 'SUCCESS' : 'FAILED'));
        
        return $participant_sent;
        
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Core Email Sending Function
 */
function sendEmail($to_email, $to_name, $subject, $html_body, $text_body = null) {
    // Create professional headers
    $headers = array();
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . ADMIN_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    $headers[] = 'X-Priority: 3';
    
    // Anti-spam headers
    $headers[] = 'X-Originating-IP: ' . ($_SERVER['SERVER_ADDR'] ?? 'unknown');
    $headers[] = 'Message-ID: <' . uniqid() . '@inventijn.nl>';
    $headers[] = 'Date: ' . date('r');
    
    // Send email
    $success = mail(
        $to_email,
        $subject,
        $html_body,
        implode("\r\n", $headers)
    );
    
    // Log email attempt
    error_log("Email attempt to $to_email - Subject: $subject - Result: " . ($success ? 'SUCCESS' : 'FAILED'));
    
    return $success;
}

/**
 * Email Template Generator
 */
function getEmailTemplate($type, $data, $result_id = null) {
    $templates = [
        'enrollment' => [
            'subject' => "Inschrijving bevestigd: {$data['training_name']}",
            'admin_subject' => "Nieuwe inschrijving: {$data['naam']} voor {$data['training_name']}"
        ],
        'interest' => [
            'subject' => "Interesse geregistreerd: {$data['training_name']}",
            'admin_subject' => "Nieuwe interesse: {$data['naam']} voor {$data['training_name']}"
        ],
        'incompany' => [
            'subject' => "Incompany aanvraag ontvangen: {$data['training_name']}",
            'admin_subject' => "Nieuwe incompany aanvraag: {$data['naam']} - {$data['training_name']}"
        ],
        'waitlist' => [
            'subject' => "Wachtlijst: {$data['training_name']}",
            'admin_subject' => "Wachtlijst toevoeging: {$data['naam']} voor {$data['training_name']}"
        ]
    ];
    
    if (!isset($templates[$type])) {
        return null;
    }
    
    $template = $templates[$type];
    
    return [
        'subject' => $template['subject'],
        'body_html' => generateEmailHTML($type, $data, $result_id),
        'body_text' => generateEmailText($type, $data, $result_id),
        'admin_subject' => $template['admin_subject']
    ];
}

/**
 * HTML Email Template Generator
 */
function generateEmailHTML($type, $data, $result_id) {
    $current_year = date('Y');
    $naam = htmlspecialchars($data['naam']);
    $training = htmlspecialchars($data['training_name']);
    
    $content = getEmailContent($type, $data, $result_id);
    
    return "
    <!DOCTYPE html>
    <html lang='nl'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>$training</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8fafc;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: white;'>
            
            <!-- Header -->
            <div style='background: linear-gradient(135deg, #3e5cc6 0%, #b998e4 100%); padding: 30px 20px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 28px; font-weight: 300;'>Inventijn</h1>
                <p style='color: #e3a1e5; margin: 10px 0 0 0; font-size: 14px;'>Praktische AI Training</p>
            </div>
            
            <!-- Content -->
            <div style='padding: 40px 30px;'>
                $content
            </div>
            
            <!-- Footer -->
            <div style='background-color: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;'>
                <p style='margin: 0 0 10px 0; color: #6b7280; font-size: 14px;'>
                    <strong>Inventijn</strong><br>
                    Praktische AI Training voor Professionals
                </p>
                <p style='margin: 0; color: #9ca3af; font-size: 12px;'>
                    Vragen? Reageer gerust op deze e-mail<br>
                    © $current_year Inventijn. Alle rechten voorbehouden.
                </p>
            </div>
        </div>
    </body>
    </html>";
}

/**
 * Email Content Based on Type
 */
function getEmailContent($type, $data, $result_id) {
    $naam = htmlspecialchars($data['naam']);
    $training = htmlspecialchars($data['training_name']);
    
    switch ($type) {
        case 'enrollment':
            return "
                <h2 style='color: #1f2937; margin: 0 0 20px 0;'>🎉 Inschrijving Bevestigd!</h2>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151;'>Beste $naam,</p>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151;'>
                    Geweldig! Je inschrijving voor <strong>$training</strong> is succesvol ontvangen.
                </p>
                
                <div style='background: #eff6ff; border-left: 4px solid #2563eb; padding: 20px; margin: 20px 0;'>
                    <h3 style='color: #1e40af; margin: 0 0 10px 0;'>Volgende stappen:</h3>
                    <ol style='color: #1e40af; margin: 0; padding-left: 20px;'>
                        <li>Je wordt doorgeleid naar de betaalpagina</li>
                        <li>Na betaling is je plaats definitief gereserveerd</li>
                        <li>Een week voor de training ontvang je alle praktische informatie</li>
                    </ol>
                </div>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151; margin-top: 30px;'>
                    Ik kijk er naar uit je te ontmoeten bij de training!<br><br>
                    
                    Met vriendelijke groet,<br>
                    <strong>Martijn Planken</strong><br>
                    <em>Trainer & AI Specialist</em>
                </p>";
                
        case 'interest':
            return "
                <h2 style='color: #1f2937; margin: 0 0 20px 0;'>📝 Interesse Geregistreerd</h2>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151;'>Beste $naam,</p>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151;'>
                    Bedankt voor je interesse in <strong>$training</strong>!
                </p>
                
                <div style='background: #ecfdf5; border-left: 4px solid #10b981; padding: 20px; margin: 20px 0;'>
                    <h3 style='color: #065f46; margin: 0 0 10px 0;'>Wat gebeurt er nu?</h3>
                    <ul style='color: #065f46; margin: 0; padding-left: 20px;'>
                        <li>Zodra we nieuwe data plannen, ben je de eerste die het hoort</li>
                        <li>Je ontvangt tijdig een uitnodiging om in te schrijven</li>
                        <li>Geen verplichtingen - je kunt altijd afzien</li>
                    </ul>
                </div>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151; margin-top: 30px;'>
                    Tot binnenkort!<br><br>
                    
                    Met vriendelijke groet,<br>
                    <strong>Martijn Planken</strong><br>
                    <em>Inventijn</em>
                </p>";
                
        case 'incompany':
            return "
                <h2 style='color: #1f2937; margin: 0 0 20px 0;'>🏢 Incompany Aanvraag Ontvangen</h2>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151;'>Beste $naam,</p>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151;'>
                    Je aanvraag voor incompany training <strong>$training</strong> is ontvangen!
                </p>
                
                <div style='background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; margin: 20px 0;'>
                    <h3 style='color: #92400e; margin: 0 0 10px 0;'>Volgende stappen:</h3>
                    <ul style='color: #92400e; margin: 0; padding-left: 20px;'>
                        <li>Ik neem binnen <strong>2 werkdagen</strong> persoonlijk contact met je op</li>
                        <li>We bespreken jullie specifieke behoeften en mogelijkheden</li>
                        <li>Ik maak een passend voorstel op maat</li>
                    </ul>
                </div>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151; margin-top: 30px;'>
                    Ik kijk uit naar ons gesprek!<br><br>
                    
                    Met vriendelijke groet,<br>
                    <strong>Martijn Planken</strong><br>
                    <em>Inventijn - AI Training op Maat</em>
                </p>";
                
        case 'waitlist':
            return "
                <h2 style='color: #1f2937; margin: 0 0 20px 0;'>⏳ Wachtlijst Bevestiging</h2>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151;'>Beste $naam,</p>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151;'>
                    De training <strong>$training</strong> is helaas vol, maar je bent toegevoegd aan de wachtlijst.
                </p>
                
                <div style='background: #fef2f2; border-left: 4px solid #ef4444; padding: 20px; margin: 20px 0;'>
                    <h3 style='color: #991b1b; margin: 0 0 10px 0;'>Wat betekent dit?</h3>
                    <ul style='color: #991b1b; margin: 0; padding-left: 20px;'>
                        <li>Bij een annulering ben je de eerste die het hoort</li>
                        <li>We planning regelmatig nieuwe data - je krijgt voorrang</li>
                        <li>Geen kosten of verplichtingen</li>
                    </ul>
                </div>
                
                <p style='font-size: 16px; line-height: 1.6; color: #374151; margin-top: 30px;'>
                    Hopelijk tot snel!<br><br>
                    
                    Met vriendelijke groet,<br>
                    <strong>Martijn Planken</strong><br>
                    <em>Inventijn</em>
                </p>";
                
        default:
            return "<p>Bedankt voor je aanmelding voor $training!</p>";
    }
}

/**
 * Plain Text Version (fallback)
 */
function generateEmailText($type, $data, $result_id) {
    $naam = $data['naam'];
    $training = $data['training_name'];
    
    return "Beste $naam,\n\nBedankt voor je aanmelding voor $training!\n\nMet vriendelijke groet,\nMartijn Planken\nInventijn";
}

/**
 * Admin Notification System
 */
function sendAdminNotification($type, $data, $result_id) {
    $email_content = getEmailTemplate($type, $data, $result_id);
    
    if (!$email_content) {
        return false;
    }
    
    $admin_body = generateAdminNotificationHTML($type, $data, $result_id);
    
    return sendEmail(
        ADMIN_EMAIL,
        'Martijn Planken',
        $email_content['admin_subject'],
        $admin_body
    );
}

/**
 * Admin Notification HTML
 */
function generateAdminNotificationHTML($type, $data, $result_id) {
    $type_labels = [
        'enrollment' => '🎓 NIEUWE INSCHRIJVING',
        'interest' => '📝 NIEUWE INTERESSE', 
        'incompany' => '🏢 INCOMPANY AANVRAAG',
        'waitlist' => '⏳ WACHTLIJST TOEVOEGING'
    ];
    
    $label = $type_labels[$type] ?? 'NIEUWE AANMELDING';
    $naam = htmlspecialchars($data['naam']);
    $email = htmlspecialchars($data['email']);
    $training = htmlspecialchars($data['training_name']);
    
    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px;'>
        <div style='background: #1f2937; color: white; padding: 20px; text-align: center;'>
            <h1 style='margin: 0; font-size: 24px;'>$label</h1>
        </div>
        
        <div style='padding: 20px; background: white;'>
            <h2 style='color: #1f2937; margin-top: 0;'>$training</h2>
            
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='font-weight: bold; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>Naam:</td><td style='padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>$naam</td></tr>
                <tr><td style='font-weight: bold; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>Email:</td><td style='padding: 8px 0; border-bottom: 1px solid #e5e7eb;'><a href='mailto:$email'>$email</a></td></tr>";
    
    if (!empty($data['telefoon'])) {
        $telefoon = htmlspecialchars($data['telefoon']);
        $html .= "<tr><td style='font-weight: bold; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>Telefoon:</td><td style='padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>$telefoon</td></tr>";
    }
    
    if (!empty($data['organisatie'])) {
        $organisatie = htmlspecialchars($data['organisatie']);
        $html .= "<tr><td style='font-weight: bold; padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>Organisatie:</td><td style='padding: 8px 0; border-bottom: 1px solid #e5e7eb;'>$organisatie</td></tr>";
    }
    
    if (!empty($data['opmerkingen'])) {
        $html .= "</table><h3 style='color: #1f2937; margin-top: 20px;'>Opmerkingen:</h3>";
        $html .= "<p style='background: #f8fafc; padding: 15px; border-radius: 8px;'>" . nl2br(htmlspecialchars($data['opmerkingen'])) . "</p><table style='width: 100%; border-collapse: collapse;'>";
    }
    
    $html .= "</table></div></div>";
    
    return $html;
}

// ===== DATABASE FUNCTIONS =====

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
                    name = ?, phone = ?, company = ?, updated_at = NOW()
                WHERE email = ?
            ");
            $stmt->execute([$naam, $telefoon, $organisatie, $email]);
            
            return $existing_user['id'];
        } else {
            // Create new user
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
        
        // Enroll user WITHOUT 'source' column (matches your database)
        $stmt = $pdo->prepare("
            INSERT INTO course_participants 
            (user_id, course_id, enrollment_date, payment_status)
            VALUES (?, ?, NOW(), 'pending')
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

// ===== MAIN PROCESSING LOGIC =====

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