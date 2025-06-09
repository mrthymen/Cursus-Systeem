<?php
/**
 * Inventijn Cursus Management System - Database Configuration  
 * Updated voor v3.3.8 - EXACT SERVER PATH FIX
 * Based on actual server structure: /var/www/vhosts/inventijn.nl/httpdocs/
 * 
 * @version 3.3.8
 * @author Inventijn Development Team + Martijn Planken
 * @date 2025-06-07
 */

// ==========================================
// COMPOSER mPDF LOADING v3.3.8 - EXACT PATHS
// ==========================================

$mpdf_loaded = false;
$mpdf_error = '';

// Based on your exact server structure from status check
$autoloader_paths = [
    // From includes subdirectory to root vendor (MOST LIKELY)
    '/var/www/vhosts/inventijn.nl/httpdocs/vendor/autoload.php',
    
    // Relative path from includes to vendor
    __DIR__ . '/../../vendor/autoload.php',
    
    // Alternative relative paths
    dirname(dirname(__DIR__)) . '/vendor/autoload.php',
    $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php',
    
    // Fallback paths
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];

error_log("🔍 mPDF Autoloader Detection v3.3.8 starting...");
error_log("Current file: " . __FILE__);
error_log("Current dir: " . __DIR__);
error_log("Document root: " . $_SERVER['DOCUMENT_ROOT']);

foreach ($autoloader_paths as $index => $path) {
    error_log("Testing autoloader path #{$index}: {$path}");
    
    if (file_exists($path)) {
        error_log("✅ Autoloader file exists: {$path}");
        try {
            require_once $path;
            error_log("✅ Autoloader loaded successfully from: " . $path);
            break;
        } catch (Exception $e) {
            error_log("❌ Autoloader load failed from " . $path . ": " . $e->getMessage());
            $mpdf_error = "Autoloader load failed: " . $e->getMessage();
        }
    } else {
        error_log("❌ Autoloader not found at: " . $path);
    }
}

// Enhanced mPDF class detection with extensive logging
error_log("🔍 Checking for mPDF classes...");

// Check declared classes for debugging
if (function_exists('get_declared_classes')) {
    $all_classes = get_declared_classes();
    $mpdf_related = array_filter($all_classes, function($class) {
        return stripos($class, 'mpdf') !== false;
    });
    
    if ($mpdf_related) {
        error_log("🔍 Found mPDF-related classes: " . implode(', ', $mpdf_related));
    } else {
        error_log("🔍 No mPDF-related classes found in " . count($all_classes) . " total classes");
    }
}

if (class_exists('Mpdf\\Mpdf')) {
    $mpdf_loaded = true;
    define('MPDF_VERSION', 'Modern v8+ (Composer)');
    define('MPDF_CLASS', 'Mpdf\\Mpdf');
    error_log("✅ Modern mPDF v8+ detected: Mpdf\\Mpdf");
    
    // Test instantiation with specific error handling
    try {
        $test_instance = new \Mpdf\Mpdf([
            'mode' => 'utf-8', 
            'format' => 'A4',
            'tempDir' => sys_get_temp_dir()
        ]);
        error_log("✅ mPDF instantiation test successful");
        unset($test_instance);
    } catch (Exception $e) {
        error_log("⚠️ mPDF class exists but instantiation failed: " . $e->getMessage());
        $mpdf_error = "mPDF instantiation failed: " . $e->getMessage();
        $mpdf_loaded = false; // Mark as not loaded if it can't be instantiated
    }
    
} elseif (class_exists('mPDF')) {
    $mpdf_loaded = true;
    define('MPDF_VERSION', 'Legacy v5.7 (Composer)');
    define('MPDF_CLASS', 'mPDF');
    error_log("✅ Legacy mPDF v5.7 detected: mPDF");
    
} else {
    $mpdf_error = 'mPDF classes not found after autoloader attempt';
    error_log("❌ " . $mpdf_error);
    
    // Additional debugging
    error_log("🔍 Checking if autoloader was actually loaded...");
    if (function_exists('spl_autoload_functions')) {
        $autoloaders = spl_autoload_functions();
        error_log("🔍 Found " . count($autoloaders) . " autoload functions");
    }
}

// Set availability flags
define('MPDF_AVAILABLE', $mpdf_loaded);
define('MPDF_ERROR', $mpdf_error);

// Final status logging
if ($mpdf_loaded) {
    error_log("🎉 Certificate System Ready: " . MPDF_VERSION);
} else {
    error_log("🚨 Certificate System Limited: " . $mpdf_error);
}

// ==========================================
// DATABASE CONFIGURATION
// ==========================================

// Database connection details
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventijn_cursus');
define('DB_USER', 'inventijn_user');
define('DB_PASS', 'sypde5-cimnab-diSdoc');

// Database character set voor emoji support
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

// ==========================================
// APPLICATION CONFIGURATION v3.3.8
// ==========================================

// Base URL voor email links en redirects
define('BASE_URL', 'https://inventijn.nl/cursus-systeem/');

// Admin email voor notificaties
define('ADMIN_EMAIL', 'martijn@planken.cc'); // Extern adres
define('SYSTEM_EMAIL', 'noreply@planken.cc'); // From-address
define('REPLY_TO_EMAIL', 'martijn@planken.cc'); // Reply-to

// Email system configuration
define('EMAIL_SEND_ENABLED', true);
define('EMAIL_QUEUE_BATCH_SIZE', 10);
define('EMAIL_RETRY_ATTEMPTS', 3);
define('EMAIL_RETRY_DELAY', 300);

// Form processing settings
define('FORM_RATE_LIMIT_SECONDS', 30);
define('DUPLICATE_PREVENTION_HOURS', 24);
define('AUTO_EMAIL_NOTIFICATIONS', true);

// Security settings
define('ACCESS_KEY_LENGTH', 32);
define('ADMIN_SESSION_TIMEOUT', 3600);
define('MAX_LOGIN_ATTEMPTS', 5);

// File upload settings
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', 'pdf,doc,docx,ppt,pptx,zip');
define('UPLOAD_PATH', 'uploads/course_materials/');

// Certificate system settings v3.3.8 - ENHANCED PATHS
define('CERTIFICATE_STORAGE_PATH', '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/certificates/');
define('CERTIFICATE_TEMP_PATH', '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/temp/');
define('CERTIFICATE_LOGO_PATH', '/var/www/vhosts/inventijn.nl/httpdocs/assets/images/logo.svg');
define('CERTIFICATE_BACKGROUND_PATH', __DIR__ . '/cert_bg.png');
define('CERTIFICATE_AUTO_EMAIL', true);
define('CERTIFICATE_VERIFICATION_URL', 'https://inventijn.nl/cursus-systeem/verify-certificate.php');

// Planning dashboard settings
define('INTEREST_ANALYTICS_DAYS', 90);
define('CONVERSION_TRACKING_ENABLED', true);
define('BULK_EMAIL_MAX_RECIPIENTS', 100);

// Course management settings  
define('DEFAULT_MAX_PARTICIPANTS', 20);
define('DEFAULT_MIN_PARTICIPANTS', 3);
define('COURSE_MATERIAL_ACCESS_HOURS_BEFORE', 24);
define('AUTOMATIC_REMINDER_HOURS', 24);

// Pricing configuration
define('PRICE_AI_BOOSTER_INTRODUCTIE', 247.00);
define('PRICE_AI_BOOSTER_VERDIEPING', 297.00);
define('PRICE_AI_BOOSTER_COMBI', 497.00);
define('PRICE_AI_BOOSTER_INCOMPANY', 2250.00);

// Development/Debug settings
define('DEBUG_MODE', false);
define('LOG_FORM_SUBMISSIONS', true);
define('LOG_EMAIL_QUEUE', true);

// ==========================================
// DATABASE CONNECTION FUNCTIONS v3.3.8
// ==========================================

/**
 * Get PDO database connection
 */
function getDatabase() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_COLLATE
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+01:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            
            if (DEBUG_MODE) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            } else {
                throw new Exception("Database verbinding mislukt. Probeer het later opnieuw.");
            }
        }
    }
    
    return $pdo;
}

/**
 * Get MySQLi database connection for Certificate System
 */
function getMySQLiDatabase() {
    static $mysqli = null;
    
    if ($mysqli === null) {
        try {
            $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($mysqli->connect_error) {
                throw new Exception("Connection failed: " . $mysqli->connect_error);
            }
            
            if (!$mysqli->set_charset(DB_CHARSET)) {
                throw new Exception("Error setting charset: " . $mysqli->error);
            }
            
            $mysqli->query("SET time_zone = '+01:00'");
            
        } catch (Exception $e) {
            error_log("MySQLi connection failed: " . $e->getMessage());
            
            if (DEBUG_MODE) {
                throw new Exception("MySQLi connection failed: " . $e->getMessage());
            } else {
                throw new Exception("Database verbinding mislukt. Probeer het later opnieuw.");
            }
        }
    }
    
    return $mysqli;
}

// ==========================================
// UTILITY FUNCTIONS v3.3.8
// ==========================================

function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

function generateSecureAccessKey() {
    return bin2hex(random_bytes(ACCESS_KEY_LENGTH / 2));
}

function isEmailEnabled() {
    return EMAIL_SEND_ENABLED && !empty(ADMIN_EMAIL);
}

function getTrainingPrice($trainingType) {
    switch (strtolower($trainingType)) {
        case 'introductie': return PRICE_AI_BOOSTER_INTRODUCTIE;
        case 'verdieping': return PRICE_AI_BOOSTER_VERDIEPING;
        case 'combi': return PRICE_AI_BOOSTER_COMBI;
        case 'incompany': return PRICE_AI_BOOSTER_INCOMPANY;
        default: return 0.00;
    }
}

function logActivity($message, $level = 'INFO') {
    if (DEBUG_MODE || LOG_FORM_SUBMISSIONS) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        error_log($logMessage, 3, 'logs/activity.log');
    }
}

function checkRateLimit($identifier) {
    $session_key = 'last_submission_' . md5($identifier);
    
    if (isset($_SESSION[$session_key])) {
        $timePassed = time() - $_SESSION[$session_key];
        if ($timePassed < FORM_RATE_LIMIT_SECONDS) {
            return false;
        }
    }
    
    $_SESSION[$session_key] = time();
    return true;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function ensureUploadDirectory() {
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
}

/**
 * Ensure certificate directories exist v3.3.8
 */
function ensureCertificateDirectories() {
    $directories = [
        '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/certificates/',
        '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/temp/',
        // Fallback relative paths
        __DIR__ . '/../certificates/',
        __DIR__ . '/../temp/'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (@mkdir($dir, 0755, true)) {
                error_log("✅ Created directory: " . $dir);
            } else {
                error_log("❌ Failed to create directory: " . $dir);
            }
        } else {
            error_log("✅ Directory exists: " . $dir);
        }
    }
}

/**
 * Check certificate system requirements v3.3.8 - ENHANCED
 */
function checkCertificateSystemRequirements() {
    $errors = [];
    $warnings = [];
    
    // Check PHP extensions
    $required_extensions = ['gd', 'mbstring', 'iconv'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Missing PHP extension: {$ext}";
        }
    }
    
    // Check mPDF availability with detailed error reporting
    if (!defined('MPDF_AVAILABLE') || !MPDF_AVAILABLE) {
        $error_detail = defined('MPDF_ERROR') ? MPDF_ERROR : 'Unknown mPDF error';
        $errors[] = "mPDF not available: " . $error_detail;
    } else {
        // Test mPDF instantiation
        try {
            if (defined('MPDF_CLASS') && MPDF_CLASS === 'Mpdf\\Mpdf') {
                $test = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
                unset($test);
            }
        } catch (Exception $e) {
            $warnings[] = "mPDF class found but instantiation test failed: " . $e->getMessage();
        }
    }
    
    // Check directories
    ensureCertificateDirectories();
    
    $cert_storage = '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/certificates/';
    $cert_temp = '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/temp/';
    
    if (!is_writable($cert_storage)) {
        $errors[] = "Certificate storage directory not writable: " . $cert_storage;
    }
    
    if (!is_writable($cert_temp)) {
        $errors[] = "Certificate temp directory not writable: " . $cert_temp;
    }
    
    // Check database tables
    try {
        $mysqli = getMySQLiDatabase();
        $required_tables = ['certificates', 'course_participants', 'users', 'courses'];
        
        foreach ($required_tables as $table) {
            $result = $mysqli->query("SHOW TABLES LIKE '{$table}'");
            if ($result->num_rows === 0) {
                $errors[] = "Missing database table: {$table}";
            }
        }
        
        // Optional tables that might not exist
        $optional_tables = ['certificate_downloads', 'certificate_verifications'];
        foreach ($optional_tables as $table) {
            $result = $mysqli->query("SHOW TABLES LIKE '{$table}'");
            if ($result->num_rows === 0) {
                $warnings[] = "Optional database table missing: {$table}";
            }
        }
        
    } catch (Exception $e) {
        $errors[] = "Database connection error: " . $e->getMessage();
    }
    
    return [
        'success' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings
    ];
}

// ==========================================
// EMAIL CONFIGURATION HELPERS
// ==========================================

function getEmailTemplate($pdo, $templateKey) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE template_key = ? AND active = 1");
        $stmt->execute([$templateKey]);
        return $stmt->fetch();
    } catch (Exception $e) {
        logActivity("Failed to get email template '{$templateKey}': " . $e->getMessage(), 'ERROR');
        return false;
    }
}

function replaceEmailVariables($text, $variables) {
    if (!is_array($variables)) {
        return $text;
    }
    
    foreach ($variables as $key => $value) {
        $text = str_replace("{{" . $key . "}}", $value, $text);
    }
    
    return $text;
}

// ==========================================
// INSTALLATION CHECK v3.3.8
// ==========================================

function checkDatabaseSchema() {
    try {
        $pdo = getDatabase();
        
        $requiredTables = [
            'users', 'courses', 'course_participants', 'course_interest',
            'course_materials', 'email_templates', 'email_queue', 
            'access_log', 'admin_users'
        ];
        
        $certificateTables = [
            'certificates'
        ];
        
        $allTables = array_merge($requiredTables, $certificateTables);
        
        foreach ($allTables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            
            if (!$stmt->fetch()) {
                throw new Exception("Required table '{$table}' not found");
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        logActivity("Database schema check failed: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// ==========================================
// ENVIRONMENT CONFIGURATION
// ==========================================

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

ensureUploadDirectory();
ensureCertificateDirectories();

// ==========================================
// VERSION INFO v3.3.8
// ==========================================

define('SYSTEM_VERSION', '3.3.8');
define('SYSTEM_RELEASE_DATE', '2025-06-07');
define('SYSTEM_NAME', 'Inventijn Cursus Management System');

function getSystemInfo() {
    $cert_status = checkCertificateSystemRequirements();
    
    return [
        'version' => SYSTEM_VERSION,
        'release_date' => SYSTEM_RELEASE_DATE,
        'name' => SYSTEM_NAME,
        'php_version' => PHP_VERSION,
        'database_schema_ok' => checkDatabaseSchema(),
        'email_enabled' => isEmailEnabled(),
        'certificate_system_ready' => $cert_status['success'],
        'certificate_errors' => $cert_status['errors'],
        'certificate_warnings' => $cert_status['warnings'],
        'mpdf_version' => defined('MPDF_VERSION') ? MPDF_VERSION : 'Not available',
        'mpdf_class' => defined('MPDF_CLASS') ? MPDF_CLASS : 'Not available',
        'mpdf_available' => defined('MPDF_AVAILABLE') ? MPDF_AVAILABLE : false,
        'debug_mode' => DEBUG_MODE
    ];
}

// ==========================================
// INITIALIZATION COMPLETE v3.3.8
// ==========================================

logActivity("Configuration v" . SYSTEM_VERSION . " loaded with exact server path targeting");

// Voor backwards compatibility
$db_host = DB_HOST;
$db_name = DB_NAME; 
$db_user = DB_USER;
$db_pass = DB_PASS;

?>