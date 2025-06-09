<?php
/**
 * Email Queue Processor v1.2 - Junkmail naar Inbox
 * Specifieke fixes om van junkmail naar inbox te gaan
 * 
 * @version 1.2.0
 * @author Inventijn Development Team  
 * @date 2025-06-07
 * @features Werkende delivery + junkmail fixes
 */

require_once 'config.php';

set_time_limit(300);
ignore_user_abort(true);

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre style='background: #f8f9fa; padding: 20px; border-radius: 8px; font-family: monospace;'>";
}

echo "=== EMAIL QUEUE PROCESSOR v1.2 (JUNKMAIL → INBOX) ===\n";
echo "Started: " . date('Y-m-d H:i:s') . "\n";
echo "Email sending: " . (EMAIL_SEND_ENABLED ? 'ENABLED' : 'DISABLED') . "\n\n";

try {
    $pdo = getDatabase();
    
    if (!EMAIL_SEND_ENABLED) {
        echo "⚠️ Email sending is DISABLED in config\n";
        echo "💡 Set EMAIL_SEND_ENABLED = true to actually send emails\n\n";
    }
    
    $batch_size = defined('EMAIL_QUEUE_BATCH_SIZE') ? EMAIL_QUEUE_BATCH_SIZE : 10;
    
    $stmt = $pdo->prepare("
        SELECT * FROM email_queue 
        WHERE status = 'pending' 
        AND (send_after IS NULL OR send_after <= NOW())
        AND attempts < ?
        ORDER BY priority ASC, created_at ASC 
        LIMIT ?
    ");
    
    $max_attempts = defined('EMAIL_RETRY_ATTEMPTS') ? EMAIL_RETRY_ATTEMPTS : 3;
    $stmt->execute([$max_attempts, $batch_size]);
    $emails = $stmt->fetchAll();
    
    if (empty($emails)) {
        echo "📭 No pending emails to process\n";
        exit;
    }
    
    echo "📧 Processing " . count($emails) . " emails with inbox optimization...\n\n";
    
    $sent_count = 0;
    $failed_count = 0;
    
    foreach ($emails as $email) {
        echo "📤 Processing email ID {$email['id']} to {$email['recipient_email']}\n";
        echo "   Subject: {$email['subject']}\n";
        echo "   Template: {$email['template_used']}\n";
        
        $stmt = $pdo->prepare("UPDATE email_queue SET status = 'sending', attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$email['id']]);
        
        $success = false;
        $error_message = '';
        
        try {
            if (EMAIL_SEND_ENABLED) {
                // INBOX-OPTIMIZED HEADERS (getest op werkende basis)
                $headers = generateInboxHeaders($email);
                
                // CONTENT OPTIMIZATION (spam trigger removal)
                $optimized_content = optimizeForInbox($email['body_html'], $email['subject']);
                
                // Send with optimizations
                $success = mail(
                    $email['recipient_email'],
                    $email['subject'],
                    $optimized_content,
                    implode("\r\n", $headers)
                );
                
                if (!$success) {
                    $error_message = 'PHP mail() function returned false';
                }
                
            } else {
                $success = true;
                echo "   📝 SIMULATION: Inbox-optimized email would be sent\n";
            }
            
        } catch (Exception $e) {
            $success = false;
            $error_message = $e->getMessage();
        }
        
        if ($success) {
            $stmt = $pdo->prepare("
                UPDATE email_queue 
                SET status = 'sent', sent_at = NOW(), error_message = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$email['id']]);
            
            echo "   ✅ Email sent with inbox optimization\n";
            $sent_count++;
            
            logActivity("Inbox-optimized email sent to {$email['recipient_email']} (template: {$email['template_used']})", 'INFO');
            
        } else {
            $stmt = $pdo->prepare("
                UPDATE email_queue 
                SET status = 'failed', error_message = ? 
                WHERE id = ?
            ");
            $stmt->execute([$error_message, $email['id']]);
            
            echo "   ❌ Email failed: $error_message\n";
            $failed_count++;
            
            logActivity("Inbox-optimized email failed to {$email['recipient_email']}: $error_message", 'ERROR');
        }
        
        echo "\n";
        usleep(150000); // 0.15 second delay voor betere reputation
    }
    
    echo "=== INBOX OPTIMIZATION COMPLETE ===\n";
    echo "✅ Sent: $sent_count\n";
    echo "❌ Failed: $failed_count\n";
    echo "📊 Total processed: " . ($sent_count + $failed_count) . "\n";
    
    $remaining = $pdo->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
    
    if ($remaining > 0) {
        echo "⏳ Remaining pending emails: $remaining\n";
    } else {
        echo "🎉 All emails processed with inbox optimization!\n";
    }
    
} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    logActivity("Inbox optimization processor fatal error: " . $e->getMessage(), 'ERROR');
    exit(1);
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] === 'GET') {
    echo "</pre>";
}

logActivity("Inbox-optimized email processor v1.2 completed: $sent_count sent, $failed_count failed", 'INFO');

/**
 * ==========================================
 * INBOX OPTIMIZATION FUNCTIONS v1.2
 * ==========================================
 */

/**
 * Generate headers optimized for inbox (not junk)
 */
function generateInboxHeaders($email_data) {
    $from_email = defined('SYSTEM_EMAIL') ? SYSTEM_EMAIL : 'noreply@planken.cc';
    $reply_to = defined('REPLY_TO_EMAIL') ? REPLY_TO_EMAIL : 'martijn@planken.cc';
    
    // Headers die inbox delivery verbeteren (getest op werkende basis)
    $headers = [
        // Professional sender (KEY voor inbox)
        "From: Martijn Planken - Inventijn <$from_email>",  // Echte naam = beter
        "Reply-To: Martijn Planken <$reply_to>",
        
        // Essential headers
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        
        // Reputation headers (simpel)
        "X-Mailer: Inventijn Training System",
        "X-Priority: 3",
        
        // Trust indicators (KEY voor niet-junk)
        "Organization: Inventijn Training",
        "X-Sender: martijn@planken.cc",
        
        // Date header (some spam filters require this)
        "Date: " . date('r')
    ];
    
    // Conditional headers (alleen voor user emails, niet admin)
    if (strpos($email_data['recipient_email'], 'inventijn.nl') === false && 
        strpos($email_data['recipient_email'], 'planken.cc') === false) {
        // Voor externe ontvangers - compliance headers
        $headers[] = "List-Unsubscribe: <mailto:martijn@planken.cc?subject=Afmelden>";
    }
    
    // Voor hoge prioriteit
    if (isset($email_data['priority']) && $email_data['priority'] <= 2) {
        $headers[] = "Importance: High";
    }
    
    return $headers;
}

/**
 * Optimize content specifically for inbox delivery
 */
function optimizeForInbox($html_content, $subject) {
    // 1. Remove common spam triggers
    $spam_replacements = [
        'Gratis' => 'Kosteloos',
        'GRATIS' => 'Kosteloos', 
        'FREE' => 'kosteloos',
        'URGENT' => 'belangrijk',
        'DRINGEND' => 'belangrijk',
        'Actie vereist' => 'reactie gewenst',
        '!!!' => '!',
        '€0' => 'geen kosten'
    ];
    
    foreach ($spam_replacements as $trigger => $replacement) {
        $html_content = str_ireplace($trigger, $replacement, $html_content);
    }
    
    // 2. Ensure proper HTML structure
    if (strpos($html_content, '<!DOCTYPE') === false && strpos($html_content, '<html') === false) {
        $html_content = wrapInBasicHTML($html_content);
    }
    
    // 3. Add required compliance elements
    $html_content = ensureCompliance($html_content);
    
    return $html_content;
}

/**
 * Wrap content in basic HTML (minimal)
 */
function wrapInBasicHTML($content) {
    return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Inventijn Training</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
' . $content . '
</body>
</html>';
}

/**
 * Ensure compliance elements exist
 */
function ensureCompliance($html_content) {
    // Check if footer with address exists
    if (strpos($html_content, 'Spoorlaan') === false) {
        // Add minimal compliance footer
        $footer = '
<div style="border-top: 1px solid #eee; padding: 15px; text-align: center; font-size: 12px; color: #666; margin-top: 20px;">
    <strong>Inventijn Training</strong><br>
    Spoorlaan 444, 5038 CG Tilburg<br>
    <a href="mailto:martijn@planken.cc">martijn@planken.cc</a>
</div>';
        
        // Insert before </body> or append
        if (strpos($html_content, '</body>') !== false) {
            $html_content = str_replace('</body>', $footer . '</body>', $html_content);
        } else {
            $html_content .= $footer;
        }
    }
    
    return $html_content;
}
?>