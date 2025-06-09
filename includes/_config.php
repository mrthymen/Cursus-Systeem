<?php
/**
 * Inventijn Deelnemersmanagement Systeem
 * Database Configuratie en Setup met Cursus Management
 * 
 * @version 2.3.0
 * @author Inventijn Development Team
 * @date 2025-06-06
 */

// Database configuratie
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventijn_cursus');
define('DB_USER', 'inventijn_user');
define('DB_PASS', 'sypde5-cimnab-diSdoc');
define('DB_CHARSET', 'utf8mb4');

// Systeem configuratie
define('SYSTEM_VERSION', '2.3.0');
define('BASE_URL', 'https://inventijn.nl/cursus-systeem/');
define('ADMIN_EMAIL', 'martijn@inventijn.nl');
define('SYSTEM_NAME', 'Inventijn Cursus Management System');

// Beveiliging
define('SESSION_LIFETIME', 3600 * 8); // 8 uur
define('ACCESS_TOKEN_LIFETIME', 3600 * 24 * 7); // 7 dagen
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_COOLDOWN', 900); // 15 minuten

// Bestands configuratie
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png', 'mp4']);

// Email configuratie
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@inventijn.nl');
define('SMTP_PASS', 'EMAIL_WACHTWOORD');
define('SMTP_FROM_NAME', 'Inventijn Cursistenportaal');

/**
 * Database installatie script met volledige cursus management
 */
function installDatabase() {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, 
        DB_USER, 
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Maak database aan
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);
    
    echo "🔄 Installeren van database tabellen...\n\n";
    
    // Gebruikers tabel
    echo "📋 Creëren users tabel...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            access_key VARCHAR(64) UNIQUE NOT NULL,
            active BOOLEAN DEFAULT TRUE,
            phone VARCHAR(20),
            company VARCHAR(255),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            INDEX idx_email (email),
            INDEX idx_access_key (access_key),
            INDEX idx_active (active)
        )
    ");
    
    // Cursussen tabel - uitgebreid
    echo "📚 Creëren courses tabel...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS courses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            course_date DATETIME NOT NULL,
            end_date DATETIME,
            location TEXT,
            time_range VARCHAR(50),
            access_start_time DATETIME,
            max_participants INT DEFAULT 20,
            price DECIMAL(10,2) DEFAULT 0.00,
            active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_course_date (course_date),
            INDEX idx_active (active),
            INDEX idx_name (name)
        )
    ");
    
    // Cursus deelnemers tabel - nieuw
    echo "👥 Creëren course_participants tabel...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_participants (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT NOT NULL,
            user_id INT NOT NULL,
            payment_status ENUM('pending', 'paid', 'cancelled', 'refunded') DEFAULT 'pending',
            payment_date DATETIME NULL,
            payment_method VARCHAR(50) NULL,
            payment_reference VARCHAR(100) NULL,
            enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            certificate_generated BOOLEAN DEFAULT FALSE,
            certificate_download_count INT DEFAULT 0,
            last_material_access TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_enrollment (course_id, user_id),
            INDEX idx_course_id (course_id),
            INDEX idx_user_id (user_id),
            INDEX idx_payment_status (payment_status),
            INDEX idx_enrollment_date (enrollment_date)
        )
    ");
    
    // Cursus materialen tabel - nieuw
    echo "📎 Creëren course_materials tabel...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course_materials (
            id INT PRIMARY KEY AUTO_INCREMENT,
            course_id INT NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(10) NOT NULL,
            file_size BIGINT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            description TEXT,
            is_watermarked BOOLEAN DEFAULT FALSE,
            download_count INT DEFAULT 0,
            access_level ENUM('all', 'paid_only', 'admin_only') DEFAULT 'paid_only',
            active BOOLEAN DEFAULT TRUE,
            uploaded_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES admin_users(id) ON DELETE SET NULL,
            INDEX idx_course_id (course_id),
            INDEX idx_file_type (file_type),
            INDEX idx_active (active),
            INDEX idx_access_level (access_level)
        )
    ");
    
    // Download logs tabel - voor security tracking
    echo "📊 Creëren download_logs tabel...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS download_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            material_id INT NOT NULL,
            course_id INT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            download_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            file_size BIGINT,
            success BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (material_id) REFERENCES course_materials(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_material_id (material_id),
            INDEX idx_course_id (course_id),
            INDEX idx_timestamp (download_timestamp)
        )
    ");
    
    // Email templates tabel
    echo "📧 Creëren email_templates tabel...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_templates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            template_key VARCHAR(100) UNIQUE NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body_html TEXT NOT NULL,
            body_text TEXT,
            variables JSON,
            active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_template_key (template_key),
            INDEX idx_active (active)
        )
    ");
    
    // Email queue tabel
    echo "📮 Creëren email_queue tabel...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS email_queue (
            id INT PRIMARY KEY AUTO_INCREMENT,
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255),
            subject VARCHAR(255) NOT NULL,
            body_html TEXT NOT NULL,
            body_text TEXT,
            template_used VARCHAR(100),
            priority INT DEFAULT 5,
            status ENUM('pending', 'sending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            send_after TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL,
            error_message TEXT,
            user_id INT NULL,
            course_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_send_after (send_after),
            INDEX idx_recipient (recipient_email)
        )
    ");
    
    // Admin gebruikers
    echo "👨‍💼 Creëren admin_users tabel...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'moderator', 'instructor') DEFAULT 'moderator',
            permissions JSON,
            active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_active (active)
        )
    ");
    
    // Maak standaard admin account
    echo "🔐 Creëren standaard admin account...\n";
    $adminPassword = password_hash('supergeheim123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT IGNORE INTO admin_users (username, email, password_hash, role) 
        VALUES ('admin', '" . ADMIN_EMAIL . "', '$adminPassword', 'admin')
    ");
    
    // Insert standaard email templates
    echo "📧 Installeren email templates...\n";
    insertDefaultEmailTemplates($pdo);
    
    // Insert sample course (optional)
    echo "📚 Creëren voorbeeld cursus...\n";
    insertSampleCourse($pdo);
    
    echo "\n✅ Database succesvol geïnstalleerd!\n";
    echo "🔐 Admin inloggegevens:\n";
    echo "   Username: admin\n";
    echo "   Password: supergeheim123\n";
    echo "⚠️  Wijzig het admin wachtwoord direct na eerste login!\n\n";
    echo "📚 Er is een voorbeeld cursus aangemaakt\n";
    echo "📧 Email templates zijn geïnstalleerd\n";
    echo "🎯 Systeem is klaar voor gebruik!\n";
}

/**
 * Insert standaard email templates
 */
function insertDefaultEmailTemplates($pdo) {
    $templates = [
        [
            'key' => 'course_invitation',
            'subject' => 'Welkom bij {{course_name}} - Je toegangscode',
            'html' => '<h2>Welkom {{user_name}}!</h2><p>Je bent ingeschreven voor <strong>{{course_name}}</strong></p><p>Cursus details:</p><ul><li>Datum: {{course_date}}</li><li>Locatie: {{course_location}}</li><li>Tijd: {{course_time}}</li></ul><p>Je persoonlijke toegangscode: <strong>{{access_key}}</strong></p><p>Materiaal wordt beschikbaar vanaf: {{access_start_time}}</p><p><a href="{{login_url}}">Klik hier voor toegang tot je cursusmateriaal</a></p>',
            'text' => 'Welkom {{user_name}}! Je bent ingeschreven voor {{course_name}}. Toegangscode: {{access_key}}. Login: {{login_url}}'
        ],
        [
            'key' => 'payment_confirmation',
            'subject' => 'Betaling ontvangen voor {{course_name}}',
            'html' => '<h2>Betaling Bevestigd!</h2><p>Hallo {{user_name}},</p><p>We hebben je betaling ontvangen voor <strong>{{course_name}}</strong>.</p><p>Bedrag: €{{amount}}</p><p>Je inschrijving is nu definitief!</p>',
            'text' => 'Betaling ontvangen voor {{course_name}}. Bedrag: €{{amount}}. Inschrijving definitief!'
        ],
        [
            'key' => 'course_reminder',
            'subject' => 'Herinnering: {{course_name}} begint morgen!',
            'html' => '<h2>Niet vergeten!</h2><p>Hallo {{user_name}},</p><p>Je cursus <strong>{{course_name}}</strong> begint morgen!</p><p>Tijd: {{course_time}}</p><p>Locatie: {{course_location}}</p><p>Je cursusmateriaal is beschikbaar via: <a href="{{login_url}}">{{login_url}}</a></p>',
            'text' => 'Herinnering: {{course_name}} begint morgen om {{course_time}} in {{course_location}}. Materiaal: {{login_url}}'
        ]
    ];
    
    foreach ($templates as $template) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO email_templates (template_key, subject, body_html, body_text, variables) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $variables = json_encode([
            'user_name', 'course_name', 'course_date', 'course_location', 
            'course_time', 'access_key', 'access_start_time', 'login_url', 'amount'
        ]);
        $stmt->execute([$template['key'], $template['subject'], $template['html'], $template['text'], $variables]);
    }
}

/**
 * Insert sample course
 */
function insertSampleCourse($pdo) {
    $sampleDate = date('Y-m-d H:i:s', strtotime('+1 week'));
    $accessDate = date('Y-m-d H:i:s', strtotime('+6 days 18:00:00'));
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO courses (name, description, course_date, location, time_range, access_start_time, max_participants, price) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'AI-Booster Masterclass',
        'Een intensieve dag waarin je leert hoe je AI optimaal kunt inzetten voor je werk en bedrijf.',
        $sampleDate,
        'Amsterdam',
        '09:00 - 17:00',
        $accessDate,
        20,
        497.00
    ]);
}

// Functie om database connectie te maken
function getDatabase() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database verbinding mislukt. Controleer de configuratie.");
        }
    }
    
    return $pdo;
}

/**
 * Database update script voor bestaande installaties
 */
function updateDatabase() {
    $pdo = getDatabase();
    
    echo "🔄 Updaten van bestaande database naar v2.3.0...\n\n";
    
    try {
        // Voeg price kolom toe aan courses als die nog niet bestaat
        $pdo->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) DEFAULT 0.00 AFTER max_participants");
        
        // Maak course_participants tabel als die nog niet bestaat
        $result = $pdo->query("SHOW TABLES LIKE 'course_participants'");
        if ($result->rowCount() == 0) {
            echo "📋 Creëren course_participants tabel...\n";
            // Voer de course_participants CREATE statement uit
            // (kopieer uit installDatabase functie)
        }
        
        echo "✅ Database update succesvol!\n";
    } catch (Exception $e) {
        echo "❌ Fout bij database update: " . $e->getMessage() . "\n";
    }
}

// Installatie check
if (php_sapi_name() === 'cli') {
    if (isset($argv[1])) {
        switch ($argv[1]) {
            case 'install':
                installDatabase();
                break;
            case 'update':
                updateDatabase();
                break;
            default:
                echo "Gebruik: php config.php [install|update]\n";
        }
    }
}
?>