<?php
/**
 * Original Certificate Management + Unified Navigation
 * Strategy: Keep 100% original functionality, only add header
 * Updated: 2025-06-09
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit;
}

// Try to add unified navigation (optional)
$has_unified_nav = false;
if (file_exists('../includes/admin_template.php')) {
    try {
        require_once '../includes/admin_template.php';
        require_once '../includes/config.php';
        
        $pdo = getDatabase();
        renderAdminHeader('Certificate Management', $pdo);
        echo '<div style="max-width: 1400px; margin: 0 auto; padding: 0 2rem;">';
        echo '<h2 style="color: #1e293b; margin-bottom: 1rem;">Certificate Management</h2>';
        $has_unified_nav = true;
        
    } catch (Exception $e) {
        $has_unified_nav = false;
    }
}

// If unified nav failed, use simple header
if (!$has_unified_nav) {
    echo '<!DOCTYPE html>
    <html lang="nl">
    <head>
        <meta charset="UTF-8">
        <title>Certificate Management</title>
    </head>
    <body style="font-family: Arial, sans-serif; margin: 20px;">
        <h1>Certificate Management</h1>
        <p><a href="index.php">← Back to Dashboard</a></p>';
}

/**
 * INVENTIJN CERTIFICATE GENERATOR v2.1.1 - LOGO PATH FIXED
 * Premium PDF Certificate Generator - Fixed logo URL
 * 
 * Key Changes v2.1.1:
 * - Fixed logo path to use full URL: https://inventijn.nl/assets/images/logo.svg
 * - All other functionality identical to v2.1
 * 
 * Author: Martijn Planken & Claude
 * Updated: 2025-06-09
 * Compatible: mPDF v8+ (with v5.7 fallback)
 */

class CertificateGenerator {
    
    private $mpdf;
    private $db;
    private $certificate_data;
    private $template_config;
    private $current_verification_token;
    private $mpdf_version;
    
    // Inventijn Brand Colors v2.1.1 - Consistent palette
    const BRAND_PRIMARY = '#3e5cc6';       // Main blue
    const BRAND_SECONDARY = '#6b80e8';     // Light blue  
    const BRAND_ACCENT = '#e3a1e5';        // Purple accent
    const BRAND_TEXT = '#2c3e50';          // Dark text
    const BRAND_LIGHT = '#f7fafc';         // Light background
    const BRAND_GOLD = '#f6e05e';          // Premium gold
    const BRAND_GRAY = '#718096';          // Subtle gray
    
    public function __construct($db_connection = null) {
        $this->db = $db_connection ?? getMySQLiDatabase();
        $this->detectMPDFVersion();
        $this->initializeTemplateConfig();
        $this->ensureDirectoriesExist();
        $this->logSystemInfo();
    }
    
    /**
     * Enhanced mPDF version detection with better logging
     */
    private function detectMPDFVersion() {
        if (class_exists('Mpdf\\Mpdf')) {
            $this->mpdf_version = 'Mpdf\\Mpdf';
            error_log("✅ CertificateGenerator v2.1.1 using modern mPDF v8+");
        } elseif (class_exists('mPDF')) {
            $this->mpdf_version = 'mPDF';
            error_log("⚠️ CertificateGenerator v2.1.1 using legacy mPDF v5.7");
        } else {
            throw new Exception("❌ No compatible mPDF installation found. Please install mPDF via Composer.");
        }
    }
    
    /**
     * Log system info for debugging
     */
    private function logSystemInfo() {
        error_log("🎯 CertificateGenerator v2.1.1 initialized:");
        error_log("   - mPDF Version: " . $this->mpdf_version);
        error_log("   - PHP Version: " . PHP_VERSION);
        error_log("   - Memory Limit: " . ini_get('memory_limit'));
        error_log("   - Extensions: GD=" . (extension_loaded('gd') ? '✅' : '❌') . 
                  ", MBString=" . (extension_loaded('mbstring') ? '✅' : '❌'));
    }
    
    /**
     * Template configuration with mPDF-optimized settings
     */
    private function initializeTemplateConfig() {
        $this->template_config = [
            'default' => [
                'format' => 'A4-L',
                'margins' => [10, 8, 10, 8], // top, right, bottom, left
                'temp_dir' => $this->getTempDirectory(),
                'font_dir' => $this->getFontDirectory(),
                'image_quality' => 90,
                'compress' => true,
                'debug' => false
            ],
            'premium' => [
                'format' => 'A4-L', 
                'margins' => [8, 6, 8, 6],
                'temp_dir' => $this->getTempDirectory(),
                'image_quality' => 95,
                'compress' => false,
                'debug' => false
            ]
        ];
    }
    
    /**
     * Main certificate generation method - Enhanced error handling
     */
    public function generateCertificate($course_participant_id, $template = 'default', $certificate_type = 'deelname') {
        $start_time = microtime(true);
        
        try {
            // Input validation
            $this->validateInputs($course_participant_id, $template, $certificate_type);
            
            // Get participant data
            $this->certificate_data = $this->getCertificateData($course_participant_id);
            if (!$this->certificate_data) {
                throw new Exception("Participant data not found for ID: $course_participant_id");
            }
            
            error_log("🎯 Generating certificate for: " . $this->certificate_data['participant_name']);
            
            // Check for existing certificate
            if ($this->hasExistingCertificate($course_participant_id, $certificate_type)) {
                throw new Exception("Certificate already exists for this participant and type");
            }
            
            // Generate verification token
            $this->current_verification_token = $this->generateVerificationToken();
            
            // Initialize mPDF with optimized settings
            $config = $this->template_config[$template] ?? $this->template_config['default'];
            $this->initializeMPDF($config);
            
            // Generate certificate HTML - NEW v2.1.1 template with fixed logo
            $html = $this->generateCertificateHTML($template, $certificate_type);
            
            if ($config['debug']) {
                error_log("📄 Generated HTML length: " . strlen($html) . " chars");
            }
            
            // Write HTML to PDF with error catching
            try {
                $this->mpdf->WriteHTML($html);
            } catch (Exception $e) {
                throw new Exception("mPDF HTML processing failed: " . $e->getMessage() . 
                                  ". This usually means CSS compatibility issues.");
            }
            
            // Generate filename and save
            $filename = $this->generateFilename($certificate_type);
            $filepath = $this->getCertificateDirectory() . '/' . $filename;
            
            // Save PDF with verification
            $this->mpdf->Output($filepath, 'F');
            
            // Verify file creation
            if (!file_exists($filepath) || filesize($filepath) < 5000) {
                throw new Exception("Certificate file generation failed or file too small (" . 
                                  (file_exists($filepath) ? filesize($filepath) : 0) . " bytes)");
            }
            
            // Save to database
            $certificate_id = $this->saveCertificateRecord($course_participant_id, $filename, $template, $certificate_type, $filepath);
            
            $generation_time = round((microtime(true) - $start_time) * 1000, 2);
            error_log("✅ Certificate generated successfully in {$generation_time}ms");
            
            return [
                'success' => true,
                'certificate_id' => $certificate_id,
                'filename' => $filename,
                'filepath' => $filepath,
                'filesize' => filesize($filepath),
                'verification_url' => $this->getVerificationURL($certificate_id),
                'mpdf_version' => $this->mpdf_version,
                'generation_time_ms' => $generation_time,
                'template_version' => '2.1.1'
            ];
            
        } catch (Exception $e) {
            $generation_time = round((microtime(true) - $start_time) * 1000, 2);
            error_log("❌ Certificate generation failed after {$generation_time}ms: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'debug' => [
                    'participant_id' => $course_participant_id,
                    'template' => $template,
                    'type' => $certificate_type,
                    'mpdf_version' => $this->mpdf_version ?? 'unknown',
                    'memory_usage' => memory_get_peak_usage(true),
                    'generation_time_ms' => $generation_time,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'template_version' => '2.1.1'
                ]
            ];
        }
    }
    
    /**
     * Input validation with better error messages
     */
    private function validateInputs($course_participant_id, $template, $certificate_type) {
        if (!is_numeric($course_participant_id) || $course_participant_id <= 0) {
            throw new Exception("Invalid participant ID: must be a positive integer");
        }
        
        if (!in_array($certificate_type, ['deelname', 'voltooiing', 'waardering'])) {
            throw new Exception("Invalid certificate type. Allowed: deelname, voltooiing, waardering");
        }
        
        if (!in_array($template, ['default', 'premium'])) {
            throw new Exception("Invalid template. Allowed: default, premium");
        }
    }
    
    /**
     * Enhanced mPDF initialization with v2.1.1 optimizations
     */
    private function initializeMPDF($config) {
        $this->validateSystemRequirements();
        
        if ($this->mpdf_version === 'Mpdf\\Mpdf') {
            // Modern mPDF v8+ configuration
            $mpdf_config = [
                'mode' => 'utf-8',
                'format' => $config['format'],
                'margin_left' => $config['margins'][3],
                'margin_right' => $config['margins'][1], 
                'margin_top' => $config['margins'][0],
                'margin_bottom' => $config['margins'][2],
                'tempDir' => $config['temp_dir'],
                'default_font_size' => 12,
                'default_font' => 'dejavusans'
            ];
            
            // Clean config
            $mpdf_config = array_filter($mpdf_config, function($value) {
                return $value !== null;
            });
            
            $this->mpdf = new \Mpdf\Mpdf($mpdf_config);
            
            // v8 specific optimizations
            if ($config['compress']) {
                $this->mpdf->SetCompression(true);
            }
            
        } else {
            // Legacy mPDF v5.7 fallback
            $this->mpdf = new mPDF(
                'utf-8',                        
                $config['format'], 
                12,                             
                'dejavusans',                   
                $config['margins'][3],          
                $config['margins'][1],          
                $config['margins'][0],          
                $config['margins'][2],          
                0,                              
                0                               
            );
        }
        
        // Universal settings
        $this->setUniversalMPDFSettings();
    }
    
    /**
     * Set universal mPDF settings
     */
    private function setUniversalMPDFSettings() {
        $data = $this->certificate_data;
        
        // Enhanced metadata
        $this->mpdf->SetTitle('Inventijn Certificaat - ' . $data['participant_name']);
        $this->mpdf->SetAuthor('Inventijn - Gedragsverandering door Inzicht');
        $this->mpdf->SetCreator('Inventijn Certificate System v2.1.1');
        $this->mpdf->SetSubject('Cursus Certificaat - ' . $data['course_name']);
        $this->mpdf->SetKeywords('inventijn, certificaat, cursus, training, gedragsverandering, ' . $data['course_name']);
        
        // Optional security
        // $this->mpdf->SetProtection(['print', 'copy']);
    }
    
    /**
     * NEW v2.1.1 - Generate mPDF-compatible certificate HTML with FIXED LOGO PATH
     */
    private function generateCertificateHTML($template, $certificate_type) {
        $data = $this->certificate_data;
        
        // Generate certificate number
        $certificate_number = $this->generateCertificateNumber($certificate_type);
        $this->certificate_data['certificate_number'] = $certificate_number;
        
        // Get the NEW v2.1.1 template with FIXED logo path
        $template_html = $this->getCompatibleTemplate($template, $certificate_type);
        
        // Build replacements
        $replacements = $this->buildTemplateReplacements($certificate_type, $certificate_number);
        
        // Apply replacements with proper HTML escaping
        foreach ($replacements as $placeholder => $value) {
            $template_html = str_replace($placeholder, $value, $template_html);
        }
        
        return $template_html;
    }
    
    /**
     * NEW v2.1.1 - mPDF Compatible Template with FIXED LOGO PATH
     */
    private function getCompatibleTemplate($template, $certificate_type) {
        // Split the template into parts to avoid syntax errors with long strings
        $template_head = $this->getTemplateHead();
        $template_styles = $this->getTemplateStyles();
        $template_body = $this->getTemplateBodyWithFixedLogo();
        
        return $template_head . $template_styles . $template_body;
    }
    
    /**
     * Template head section
     */
    private function getTemplateHead() {
        return '<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <title>Certificaat - {{participant_name}}</title>';
    }
    
    /**
     * Template styles - separated to avoid syntax errors
     */
    private function getTemplateStyles() {
        return '  <style>
    /* v2.1.1 - Final Inventijn Template - Fixed Logo Path */
    /* Martijn Planken - Optimized voor mPDF */
    
    @page {
      size: A4 landscape;
      margin: 0;
    }

    body {
      font-family: "DejaVu Sans", Arial, sans-serif;
      font-size: 14pt;
      color: #2c3e50;
      background-color: #ffffff;
      margin: 0;
      padding: 0;
    }

    .page-wrapper {
      width: 100%;
      height: 100vh;
      background-image: url("/cursus-systeem/includes/cert_bg.png");
      background-size: cover;
      background-repeat: no-repeat;
      background-position: center center;
      padding: 40pt;
      box-sizing: border-box;
    }

    .certificate {
      width: 100%;
      height: 100%;
      border: 2pt solid #3e5cc6;
      border-radius: 20pt;
      padding: 30pt;
      background-color: transparent;
      box-sizing: border-box;
      position: relative;
    }

    .certificate::before {
      content: "";
      position: absolute;
      top: 15pt;
      left: 15pt;
      right: 15pt;
      bottom: 15pt;
      border: 1pt dashed rgba(107, 128, 232, 0.2);
      border-radius: 15pt;
      pointer-events: none;
    }

    .header {
      text-align: center;
      margin-bottom: 20pt;
    }

    .logo {
      width: 150pt;
      height: auto;
      margin-bottom: 10pt;
    }

    .certificate-title {
      font-size: 60pt;
      color: #3e5cc6;
      font-weight: bold;
      text-shadow: 1pt 1pt 3pt rgba(255,255,255,0.8);
    }

    .certificate-subtitle {
      font-size: 18pt;
      color: #555;
      font-style: italic;
      text-shadow: 1pt 1pt 2pt rgba(255,255,255,0.6);
    }

    .content {
      text-align: center;
      margin: 30pt 0;
    }

    .awarded-to {
      font-size: 14pt;
      text-transform: uppercase;
      color: #777;
      letter-spacing: 1pt;
      text-shadow: 1pt 1pt 2pt rgba(255,255,255,0.6);
    }

    .recipient-name {
      font-size: 32pt;
      font-weight: bold;
      color: #e3a1e5;
      border-bottom: 2pt solid #6b80e8;
      display: inline-block;
      padding-bottom: 5pt;
      margin: 10pt 0 20pt;
      text-shadow: 1pt 1pt 3pt rgba(255,255,255,0.8);
    }

    .achievement-text {
      font-size: 16pt;
      color: #333;
      margin-bottom: 10pt;
      text-shadow: 1pt 1pt 2pt rgba(255,255,255,0.6);
    }

    .course-title {
      font-size: 24pt;
      font-weight: bold;
      color: #6b80e8;
      margin: 10pt 0;
      text-shadow: 1pt 1pt 3pt rgba(255,255,255,0.8);
    }

    .course-details {
      font-size: 12pt;
      color: #444;
      margin-top: 10pt;
      background-color: rgba(255, 255, 255, 0.7);
      padding: 8pt 12pt;
      border-radius: 6pt;
      border-left: 3pt solid #3e5cc6;
      display: inline-block;
      text-align: left;
    }

    .course-details div {
      margin: 2pt 0;
    }

    .footer {
      margin-top: 40pt;
      width: 100%;
    }

    .footer-table {
      width: 100%;
      border-collapse: collapse;
    }

    .footer-table td {
      vertical-align: top;
      padding: 10pt;
      background-color: rgba(255, 255, 255, 0.6);
    }

    .signature-line {
      border-top: 1pt solid #2c3e50;
      width: 150pt;
      margin-bottom: 5pt;
    }

    .verification {
      font-size: 10pt;
      color: #555;
      text-align: center;
      background-color: rgba(240, 244, 255, 0.8);
      padding: 6pt;
      border-radius: 4pt;
      border: 1pt solid #3e5cc6;
    }

    .certificate-number {
      font-weight: bold;
      margin-bottom: 2pt;
      color: #3e5cc6;
    }

    .date-issued {
      font-size: 10pt;
      color: #777;
      text-align: right;
    }

    .watermark {
      position: absolute;
      top: 45%;
      left: 35%;
      font-size: 50pt;
      color: rgba(185, 152, 228, 0.03);
      font-weight: bold;
      transform: rotate(-15deg);
      pointer-events: none;
      z-index: 1;
    }

    .quality-seal {
      position: absolute;
      top: 15pt;
      right: 15pt;
      width: 55pt;
      height: 55pt;
      background: rgba(246, 224, 94, 0.9);
      border-radius: 50%;
      border: 2pt solid #3e5cc6;
      text-align: center;
      font-size: 7pt;
      font-weight: bold;
      color: #2c3e50;
      z-index: 10;
      padding-top: 12pt;
      line-height: 1.1;
    }

    @media print {
      .page-wrapper {
        background-color: white;
      }
      
      .certificate {
        background-color: transparent;
      }
    }
  </style>
</head>';
    }
    
    /**
     * Template body section with FIXED LOGO PATH
     */
    private function getTemplateBodyWithFixedLogo() {
        return '<body>
  <div class="page-wrapper">
    <div class="certificate">
      <!-- Watermark -->
      <div class="watermark">INVENTIJN</div>
      
      <!-- Quality seal -->
      <div class="quality-seal">
        CERTIFIED<br>
        QUALITY<br>
        2025
      </div>
      
      <div class="header">
        <img src="https://inventijn.nl/assets/images/logo.svg" class="logo" alt="Inventijn Logo">
        <div class="certificate-title">Certificaat</div>
        <div class="certificate-subtitle">{{certificate_subtitle}}</div>
      </div>

      <div class="content">
        <div class="awarded-to">toegekend aan</div>
        <div class="recipient-name">{{participant_name}}</div>
        <div class="achievement-text">{{achievement_text}}</div>
        <div class="course-title">{{course_title}}</div>
        <div class="course-details">
          <div><strong>Datum:</strong> {{course_date_formatted}}</div>
          <div><strong>Locatie:</strong> {{course_location}}</div>
          <div><strong>Trainer:</strong> {{instructor_name}}</div>
        </div>
      </div>

      <div class="footer">
        <table class="footer-table">
          <tr>
            <td style="text-align: center;">
              <div class="signature-line"></div>
              <div>Cursusleider</div>
              <strong>{{instructor_name}}</strong>
            </td>
            <td class="verification">
              <div class="certificate-number">{{certificate_number}}</div>
              <div>Verificatie: inventijn.nl/verify</div>
            </td>
            <td class="date-issued">
              <div><strong>Uitgereikt op:</strong></div>
              <div>{{issue_date}}</div>
            </td>
          </tr>
        </table>
      </div>
    </div>
  </div>
</body>
</html>';
    }
    
    /**
     * Build template replacements with enhanced data
     */
    private function buildTemplateReplacements($certificate_type, $certificate_number) {
        $data = $this->certificate_data;
        
        // Certificate type translations
        $type_translations = [
            'deelname' => [
                'subtitle' => 'van deelname', 
                'achievement' => 'voor succesvolle deelname aan'
            ],
            'voltooiing' => [
                'subtitle' => 'van voltooiing', 
                'achievement' => 'voor het succesvol voltooien van'
            ],
            'waardering' => [
                'subtitle' => 'van waardering', 
                'achievement' => 'als waardering voor de bijdrage aan'
            ]
        ];
        
        $type_config = $type_translations[$certificate_type] ?? $type_translations['deelname'];
        
        return [
            '{{participant_name}}' => htmlspecialchars($data['participant_name']),
            '{{participant_email}}' => htmlspecialchars($data['participant_email']),
            '{{participant_company}}' => htmlspecialchars($data['participant_company'] ?? ''),
            '{{certificate_subtitle}}' => $type_config['subtitle'],
            '{{achievement_text}}' => $type_config['achievement'],
            '{{course_title}}' => htmlspecialchars($data['course_name']),
            '{{course_date_formatted}}' => $this->formatDate($data['course_date']),
            '{{course_location}}' => htmlspecialchars($data['course_location'] ?? 'Online'),
            '{{instructor_name}}' => htmlspecialchars($data['instructor_name'] ?? 'Martijn Planken'),
            '{{certificate_number}}' => $certificate_number,
            '{{verification_url}}' => $this->getVerificationURL(),
            '{{verification_token}}' => $this->current_verification_token,
            '{{issue_date}}' => date('d-m-Y'),
            '{{issue_year}}' => date('Y'),
            '{{current_timestamp}}' => date('Y-m-d H:i:s')
        ];
    }
    
    // [ALL OTHER METHODS REMAIN THE SAME - keeping this brief for space]
    // Copy all remaining methods from the original CertificateGenerator.php
    
    /**
     * Get certificate data with enhanced error handling
     */
    private function getCertificateData($course_participant_id) {
        try {
            $query = "
                SELECT 
                    cp.id as course_participant_id,
                    cp.user_id,
                    cp.course_id,
                    cp.enrollment_date,
                    cp.payment_status,
                    u.name as participant_name,
                    u.email as participant_email,
                    u.company as participant_company,
                    u.phone as participant_phone,
                    c.name as course_name,
                    c.course_date,
                    c.location as course_location,
                    c.instructor_name,
                    c.max_participants,
                    c.description as course_description
                FROM course_participants cp
                JOIN users u ON cp.user_id = u.id
                JOIN courses c ON cp.course_id = c.id
                WHERE cp.id = ? AND cp.payment_status IN ('paid', 'pending')
            ";
            
            $stmt = $this->db->prepare($query);
            if (!$stmt) {
                throw new Exception("Database prepare failed: " . $this->db->error);
            }
            
            $stmt->bind_param('i', $course_participant_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("No participant found with ID $course_participant_id or payment not confirmed");
            }
            
            return $result->fetch_assoc();
            
        } catch (Exception $e) {
            error_log("❌ getCertificateData failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate unique certificate number with better format
     */
    private function generateCertificateNumber($certificate_type) {
        $prefix = strtoupper(substr($certificate_type, 0, 3));
        $year = date('Y');
        $month = date('m');
        
        try {
            $query = "SELECT COUNT(*) + 1 as next_number FROM certificates WHERE certificate_type = ? AND YEAR(generated_at) = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('si', $certificate_type, $year);
            $stmt->execute();
            $next_number = $stmt->get_result()->fetch_assoc()['next_number'];
            
            return sprintf('INV-%s-%s-%s-%04d', $year, $month, $prefix, $next_number);
            
        } catch (Exception $e) {
            error_log("⚠️ Certificate number generation failed, using fallback: " . $e->getMessage());
            return sprintf('INV-%s-%s-%s-%04d', $year, $month, $prefix, rand(1000, 9999));
        }
    }
    
    /**
     * Check if certificate already exists
     */
    private function hasExistingCertificate($course_participant_id, $certificate_type) {
        try {
            $query = "SELECT id FROM certificates WHERE course_participant_id = ? AND certificate_type = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('is', $course_participant_id, $certificate_type);
            $stmt->execute();
            
            return $stmt->get_result()->num_rows > 0;
        } catch (Exception $e) {
            error_log("⚠️ hasExistingCertificate check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhanced system requirements validation
     */
    private function validateSystemRequirements() {
        $requirements = [
            'mbstring' => 'mbstring extension required for text processing',
            'gd' => 'GD extension required for image processing',
            'iconv' => 'iconv extension recommended for character encoding'
        ];
        
        $missing = [];
        foreach ($requirements as $ext => $message) {
            if (!extension_loaded($ext)) {
                $missing[] = $message;
            }
        }
        
        if ($missing) {
            throw new Exception("Missing PHP extensions: " . implode(', ', $missing));
        }
    }
    
    /**
     * Enhanced certificate record saving
     */
    private function saveCertificateRecord($course_participant_id, $filename, $template, $certificate_type, $filepath) {
        try {
            $verification_token = $this->current_verification_token;
            $pdf_hash = hash_file('sha256', $filepath);
            $pdf_size = filesize($filepath);
            $admin_user = $_SESSION['admin_user'] ?? 'system';
            
            $query = "
                INSERT INTO certificates (
                    course_participant_id, course_id, user_id, 
                    certificate_type, template_used, generated_by_admin,
                    certificate_number, pdf_filename, pdf_filesize, pdf_hash,
                    verification_token, status, generated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'generated', NOW())
            ";
            
            $data = $this->certificate_data;
            $stmt = $this->db->prepare($query);
            $stmt->bind_param(
                'iiisssssiss',
                $course_participant_id,
                $data['course_id'],
                $data['user_id'],
                $certificate_type,
                $template,
                $admin_user,
                $data['certificate_number'],
                $filename,
                $pdf_size,
                $pdf_hash,
                $verification_token
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Database insert failed: " . $stmt->error);
            }
            
            return $this->db->insert_id;
            
        } catch (Exception $e) {
            error_log("❌ saveCertificateRecord failed: " . $e->getMessage());
            throw new Exception("Failed to save certificate record: " . $e->getMessage());
        }
    }
    
    /**
     * Directory management with better error handling
     */
    private function ensureDirectoriesExist() {
        $directories = [
            $this->getCertificateDirectory(),
            $this->getTempDirectory()
        ];
        
        foreach ($directories as $dir) {
            if ($dir && !is_dir($dir)) {
                if (!@mkdir($dir, 0755, true)) {
                    error_log("⚠️ Could not create directory: $dir");
                }
            }
        }
    }
    
    /**
     * Get certificate directory with fallbacks
     */
    private function getCertificateDirectory() {
        $possible_dirs = [
            '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/certificates/',
            $_SERVER['DOCUMENT_ROOT'] . '/cursus-systeem/certificates/',
            dirname(__DIR__) . '/certificates/',
            __DIR__ . '/certificates/'
        ];
        
        foreach ($possible_dirs as $dir) {
            if (is_dir($dir) || @mkdir($dir, 0755, true)) {
                return $dir;
            }
        }
        
        return sys_get_temp_dir() . '/inventijn_certificates/';
    }
    
    /**
     * Get temp directory with fallbacks
     */
    private function getTempDirectory() {
        $possible_dirs = [
            '/var/www/vhosts/inventijn.nl/httpdocs/cursus-systeem/temp/',
            $_SERVER['DOCUMENT_ROOT'] . '/cursus-systeem/temp/',
            dirname(__DIR__) . '/temp/',
            __DIR__ . '/temp/'
        ];
        
        foreach ($possible_dirs as $dir) {
            if (is_dir($dir) || @mkdir($dir, 0755, true)) {
                return $dir;
            }
        }
        
        return sys_get_temp_dir();
    }
    
    /**
     * Get font directory
     */
    private function getFontDirectory() {
        return null; // Use mPDF default fonts for maximum compatibility
    }
    
    /**
     * Generate secure filename
     */
    private function generateFilename($certificate_type) {
        $data = $this->certificate_data;
        $safe_name = preg_replace('/[^a-zA-Z0-9-]/', '', str_replace(' ', '-', strtolower($data['participant_name'])));
        $safe_course = preg_replace('/[^a-zA-Z0-9-]/', '', str_replace(' ', '-', strtolower($data['course_name'])));
        $timestamp = date('YmdHis');
        
        return sprintf(
            'INV-%s-%s-%s-%s-%s.pdf',
            date('Y'),
            $certificate_type,
            substr($safe_course, 0, 20),
            substr($safe_name, 0, 15),
            $timestamp
        );
    }
    
    /**
     * Generate verification token
     */
    private function generateVerificationToken() {
        return hash('sha256', uniqid(microtime(true), true));
    }
    
    /**
     * Get verification URL
     */
    private function getVerificationURL($certificate_id = null) {
        $token = $this->current_verification_token;
        return 'https://inventijn.nl/cursus-systeem/verify-certificate.php?token=' . $token;
    }
    
    /**
     * Format date for Dutch display
     */
    private function formatDate($date) {
        if (empty($date)) return 'Datum onbekend';
        
        $months = [
            1 => 'januari', 2 => 'februari', 3 => 'maart', 4 => 'april',
            5 => 'mei', 6 => 'juni', 7 => 'juli', 8 => 'augustus',
            9 => 'september', 10 => 'oktober', 11 => 'november', 12 => 'december'
        ];
        
        $timestamp = strtotime($date);
        if (!$timestamp) return 'Datum onbekend';
        
        $day = date('j', $timestamp);
        $month = $months[date('n', $timestamp)];
        $year = date('Y', $timestamp);
        
        return "$day $month $year";
    }
    
    /**
     * Enhanced email sending with better templates (existing method enhanced)
     */
    public function sendCertificateByEmail($certificate_id, $recipient_email = null) {
        try {
            // Get certificate data
            $query = "
                SELECT 
                    c.*,
                    u.name as participant_name, 
                    u.email as participant_email, 
                    course.name as course_name 
                FROM certificates c 
                JOIN course_participants cp ON c.course_participant_id = cp.id
                JOIN users u ON cp.user_id = u.id
                JOIN courses course ON c.course_id = course.id
                WHERE c.id = ?
            ";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('i', $certificate_id);
            $stmt->execute();
            $cert = $stmt->get_result()->fetch_assoc();
            
            if (!$cert) {
                return ['success' => false, 'error' => 'Certificate not found'];
            }
            
            $to_email = $recipient_email ?? $cert['participant_email'];
            $filepath = $this->getCertificateDirectory() . '/' . $cert['pdf_filename'];
            
            if (!file_exists($filepath)) {
                return ['success' => false, 'error' => 'Certificate file not found at: ' . $filepath];
            }
            
            // Enhanced email content
            $subject = '🎓 Uw Inventijn Certificaat - ' . $cert['course_name'];
            $message = $this->buildEmailHTML($cert);
            
            // Send email
            $success = $this->sendEnhancedEmail($to_email, $subject, $message, $filepath, $cert['pdf_filename']);
            
            if ($success) {
                // Update certificate record
                $query = "UPDATE certificates SET email_sent_at = NOW(), status = 'sent' WHERE id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param('i', $certificate_id);
                $stmt->execute();
            }
            
            return [
                'success' => $success,
                'recipient' => $to_email,
                'filename' => $cert['pdf_filename']
            ];
            
        } catch (Exception $e) {
            error_log("❌ Email sending failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Build enhanced email HTML with v2.1.1 styling
     */
    private function buildEmailHTML($cert) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, " . self::BRAND_PRIMARY . " 0%, " . self::BRAND_SECONDARY . " 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: white; padding: 30px; }
                .cert-details { background: " . self::BRAND_LIGHT . "; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid " . self::BRAND_PRIMARY . "; }
                .button { display: inline-block; background: " . self::BRAND_PRIMARY . "; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎓 Gefeliciteerd!</h1>
                    <p>Uw Inventijn Certificaat is klaar</p>
                </div>
                
                <div class='content'>
                    <p>Beste " . htmlspecialchars($cert['participant_name']) . ",</p>
                    
                    <p>Het was een genoegen u te verwelkomen in onze cursus. In de bijlage vindt u uw officiële certificaat.</p>
                    
                    <div class='cert-details'>
                        <h3>📋 Certificaat Details</h3>
                        <p><strong>Cursus:</strong> " . htmlspecialchars($cert['course_name']) . "</p>
                        <p><strong>Type:</strong> " . ucfirst($cert['certificate_type']) . "</p>
                        <p><strong>Certificaatnummer:</strong> " . htmlspecialchars($cert['certificate_number']) . "</p>
                        <p><strong>Uitgegeven:</strong> " . date('d-m-Y', strtotime($cert['generated_at'])) . "</p>
                    </div>
                    
                    <p>🔐 <strong>Verificatie:</strong> De echtheid van dit certificaat kunt u verifiëren via onze website.</p>
                    
                    <a href='" . $this->getVerificationURL() . "' class='button'>Verificeer Certificaat</a>
                    
                    <h3>🚀 Wat nu?</h3>
                    <ul>
                        <li>Voeg het certificaat toe aan uw LinkedIn profiel</li>
                        <li>Deel uw nieuwe vaardigheden met uw netwerk</li>
                        <li>Bekijk onze andere cursussen voor verdere ontwikkeling</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p><strong>Inventijn</strong> - Gedragsverandering door Inzicht</p>
                    <p>Certificate System v2.1.1 | <a href='https://inventijn.nl'>inventijn.nl</a></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Send enhanced email with attachment
     */
    private function sendEnhancedEmail($to, $subject, $html_content, $attachment_path, $attachment_name) {
        $boundary = md5(uniqid(time()));
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'From: Inventijn Certificaten <noreply@inventijn.nl>',
            'Reply-To: info@inventijn.nl',
            'X-Mailer: Inventijn Certificate System v2.1.1',
            'X-Priority: 1'
        ];
        
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $html_content . "\r\n\r\n";
        
        // Attach PDF
        if (file_exists($attachment_path)) {
            $attachment_content = file_get_contents($attachment_path);
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: application/pdf; name=\"{$attachment_name}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$attachment_name}\"\r\n\r\n";
            $message .= chunk_split(base64_encode($attachment_content)) . "\r\n";
        }
        
        $message .= "--{$boundary}--";
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
}

if ($has_unified_nav) {
    echo '</div>';
    if (function_exists('renderAdminFooter')) {
        renderAdminFooter();
    } else {
        echo '</body></html>';
    }
} else {
    echo '</body></html>';
}
?>