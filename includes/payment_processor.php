<?php
/**
 * Payment Processor - Cursus Systeem v6.2.0
 * Handles payment method processing after enrollment
 * Supports: iDEAL (Pay.nl) and Invoice generation
 * Updated: 2025-06-10
 */

session_start();
require_once 'config.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json; charset=utf-8');

// Security: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate session data
if (!isset($_SESSION['pending_enrollment_id']) || !isset($_SESSION['pending_course_id'])) {
    echo json_encode(['success' => false, 'message' => 'No pending enrollment found']);
    exit;
}

$enrollment_id = $_SESSION['pending_enrollment_id'];
$course_id = $_SESSION['pending_course_id'];

// Get database connection
try {
    $pdo = getDatabase();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Validate input data
$required_fields = ['enrollment_id', 'course_id', 'amount', 'payment_method'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$data = [
    'enrollment_id' => (int)$_POST['enrollment_id'],
    'course_id' => (int)$_POST['course_id'],
    'amount' => (float)$_POST['amount'],
    'payment_method' => trim($_POST['payment_method']),
    
    // Invoice specific fields
    'bedrijfsnaam' => trim($_POST['bedrijfsnaam'] ?? ''),
    'btw_nummer' => trim($_POST['btw_nummer'] ?? ''),
    'factuuradres' => trim($_POST['factuuradres'] ?? ''),
    'afdeling' => trim($_POST['afdeling'] ?? ''),
    'referentie' => trim($_POST['referentie'] ?? ''),
];

// Validate enrollment exists and is pending
try {
    $stmt = $pdo->prepare("
        SELECT cp.*, u.name, u.email, c.name as course_name, c.price
        FROM course_participants cp
        JOIN users u ON cp.user_id = u.id
        JOIN courses c ON cp.course_id = c.id
        WHERE cp.id = ? AND cp.payment_status = 'pending'
    ");
    $stmt->execute([$enrollment_id]);
    $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$enrollment) {
        echo json_encode(['success' => false, 'message' => 'Enrollment not found or not pending']);
        exit;
    }
    
    // Validate amount matches course price
    if (abs($data['amount'] - $enrollment['price']) > 0.01) {
        echo json_encode(['success' => false, 'message' => 'Amount mismatch']);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error validating enrollment: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error validating enrollment']);
    exit;
}

// Process payment based on method
try {
    $pdo->beginTransaction();
    
    if ($data['payment_method'] === 'ideal') {
        $result = processIdealPayment($pdo, $enrollment, $data);
    } elseif ($data['payment_method'] === 'invoice') {
        $result = processInvoicePayment($pdo, $enrollment, $data);
    } else {
        throw new Exception('Invalid payment method');
    }
    
    if ($result['success']) {
        $pdo->commit();
        
        // Clear session data
        unset($_SESSION['pending_enrollment_id'], $_SESSION['pending_course_id'], $_SESSION['enrollment_user_id']);
        
        echo json_encode($result);
    } else {
        $pdo->rollback();
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Payment processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Payment processing failed: ' . $e->getMessage()]);
}

/**
 * Process iDEAL payment
 */
function processIdealPayment($pdo, $enrollment, $data) {
    try {
        // Generate transaction reference
        $transaction_ref = 'INV-' . date('Ymd') . '-' . str_pad($enrollment['id'], 4, '0', STR_PAD_LEFT);
        
        // For now, simulate iDEAL integration
        // In production, integrate with Pay.nl or other payment provider
        
        // Update enrollment with payment info
        $stmt = $pdo->prepare("
            UPDATE course_participants 
            SET payment_method = 'ideal', 
                payment_reference = ?,
                payment_status = 'processing',
                payment_date = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$transaction_ref, $enrollment['id']]);
        
        // Create payment record
        $stmt = $pdo->prepare("
            INSERT INTO payments (enrollment_id, course_id, amount, payment_method, transaction_reference, status, created_at)
            VALUES (?, ?, ?, 'ideal', ?, 'processing', NOW())
        ");
        $stmt->execute([
            $enrollment['id'],
            $enrollment['course_id'],
            $data['amount'],
            $transaction_ref
        ]);
        
        // Log activity
        logActivity("iDEAL payment initiated for enrollment {$enrollment['id']}, amount: €{$data['amount']}", 'INFO');
        
        // In production, return actual iDEAL payment URL
        $payment_url = generateIdealPaymentUrl($transaction_ref, $data['amount'], $enrollment);
        
        return [
            'success' => true,
            'payment_method' => 'ideal',
            'redirect_url' => $payment_url,
            'transaction_reference' => $transaction_ref,
            'message' => 'Redirecting to iDEAL payment'
        ];
        
    } catch (Exception $e) {
        error_log("iDEAL payment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'iDEAL payment failed'];
    }
}

/**
 * Process invoice payment
 */
function processInvoicePayment($pdo, $enrollment, $data) {
    try {
        // Validate required invoice fields
        if (empty($data['bedrijfsnaam']) || empty($data['factuuradres'])) {
            return ['success' => false, 'message' => 'Bedrijfsnaam en factuuradres zijn verplicht'];
        }
        
        // Generate invoice number
        $invoice_number = generateInvoiceNumber($pdo);
        
        // Calculate BTW (21%)
        $btw_rate = 0.21;
        $amount_excl_btw = $data['amount'];
        $btw_amount = $amount_excl_btw * $btw_rate;
        $amount_incl_btw = $amount_excl_btw + $btw_amount;
        
        // Update enrollment with invoice info
        $stmt = $pdo->prepare("
            UPDATE course_participants 
            SET payment_method = 'invoice',
                payment_reference = ?,
                payment_status = 'invoice_sent',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$invoice_number, $enrollment['id']]);
        
        // Create invoice record
        $stmt = $pdo->prepare("
            INSERT INTO invoices (
                enrollment_id, course_id, invoice_number, 
                bedrijfsnaam, btw_nummer, factuuradres, afdeling, referentie,
                amount_excl_btw, btw_rate, btw_amount, amount_incl_btw,
                status, created_at, due_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY))
        ");
        
        $stmt->execute([
            $enrollment['id'],
            $enrollment['course_id'],
            $invoice_number,
            $data['bedrijfsnaam'],
            $data['btw_nummer'],
            $data['factuuradres'],
            $data['afdeling'],
            $data['referentie'],
            $amount_excl_btw,
            $btw_rate,
            $btw_amount,
            $amount_incl_btw
        ]);
        
        // Send invoice email
        sendInvoiceEmail($enrollment, $invoice_number, $data);
        
        // Log activity
        logActivity("Invoice generated for enrollment {$enrollment['id']}: $invoice_number, amount: €$amount_incl_btw", 'INFO');
        
        return [
            'success' => true,
            'payment_method' => 'invoice',
            'invoice_number' => $invoice_number,
            'amount_incl_btw' => $amount_incl_btw,
            'message' => 'Factuur aangemaakt en verzonden'
        ];
        
    } catch (Exception $e) {
        error_log("Invoice generation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Factuur aanmaken mislukt'];
    }
}

/**
 * Generate unique invoice number
 */
function generateInvoiceNumber($pdo) {
    $year = date('Y');
    $prefix = "INV{$year}";
    
    // Get next sequence number for this year
    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(invoice_number, 8) AS UNSIGNED)) as max_num
        FROM invoices 
        WHERE invoice_number LIKE ?
    ");
    $stmt->execute([$prefix . '%']);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $next_num = ($result['max_num'] ?? 0) + 1;
    
    return $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate iDEAL payment URL (simulation)
 */
function generateIdealPaymentUrl($transaction_ref, $amount, $enrollment) {
    // In production, integrate with actual payment provider
    // For now, simulate with a confirmation page
    
    $base_url = 'https://' . $_SERVER['HTTP_HOST'];
    $return_url = $base_url . '/betaling-bevestiging.php';
    
    // Add parameters for simulation
    $params = [
        'method' => 'ideal',
        'transaction_ref' => $transaction_ref,
        'amount' => $amount,
        'enrollment_id' => $enrollment['id'],
        'status' => 'processing'
    ];
    
    return $return_url . '?' . http_build_query($params);
}

/**
 * Send invoice email
 */
function sendInvoiceEmail($enrollment, $invoice_number, $invoice_data) {
    // TODO: Implement actual email sending
    // For now, just log
    logActivity("Should send invoice email to {$enrollment['email']} for invoice $invoice_number", 'INFO');
    
    return true;
}

/**
 * Log activity
 */
function logActivity($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    error_log("[$timestamp] [$level] [$ip] $message");
}

/**
 * Create payments table if not exists
 */
function createPaymentTables($pdo) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                enrollment_id INT NOT NULL,
                course_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(20) NOT NULL,
                transaction_reference VARCHAR(100),
                status VARCHAR(20) DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (enrollment_id) REFERENCES course_participants(id),
                FOREIGN KEY (course_id) REFERENCES courses(id),
                INDEX idx_enrollment (enrollment_id),
                INDEX idx_transaction (transaction_reference),
                INDEX idx_status (status)
            )
        ");
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                enrollment_id INT NOT NULL,
                course_id INT NOT NULL,
                invoice_number VARCHAR(20) UNIQUE NOT NULL,
                bedrijfsnaam VARCHAR(255) NOT NULL,
                btw_nummer VARCHAR(50),
                factuuradres TEXT NOT NULL,
                afdeling VARCHAR(100),
                referentie VARCHAR(100),
                amount_excl_btw DECIMAL(10,2) NOT NULL,
                btw_rate DECIMAL(4,3) NOT NULL,
                btw_amount DECIMAL(10,2) NOT NULL,
                amount_incl_btw DECIMAL(10,2) NOT NULL,
                status VARCHAR(20) DEFAULT 'sent',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                due_date DATE NOT NULL,
                paid_at DATETIME NULL,
                FOREIGN KEY (enrollment_id) REFERENCES course_participants(id),
                FOREIGN KEY (course_id) REFERENCES courses(id),
                INDEX idx_enrollment (enrollment_id),
                INDEX idx_invoice_number (invoice_number),
                INDEX idx_status (status),
                INDEX idx_due_date (due_date)
            )
        ");
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to create payment tables: " . $e->getMessage());
        return false;
    }
}

// Initialize payment tables
createPaymentTables($pdo);
?>
