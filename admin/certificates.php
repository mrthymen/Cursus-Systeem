<?php
/**
 * Complete certificates.php v4.1.0
 * Inventijn Cursus Systeem
 * 
 * Volledig werkende certificaten beheer met header fixes
 */

// Start output buffering to prevent header issues
ob_start();

// Include configuration
require_once('../includes/config.php');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit();
}

// === ACTION HANDLERS (BEFORE ANY HTML OUTPUT) ===

// Handle download requests
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['cert_id'])) {
    $cert_id = intval($_GET['cert_id']);
    
    try {
        $stmt = $pdo->prepare("SELECT c.*, cp.name as participant_name, cr.title as course_title 
                              FROM certificates c 
                              LEFT JOIN course_participants cp ON c.course_participant_id = cp.id 
                              LEFT JOIN courses cr ON cp.course_id = cr.id 
                              WHERE c.id = ?");
        $stmt->execute([$cert_id]);
        $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($certificate) {
            // Update download count
            $updateStmt = $pdo->prepare("UPDATE certificates SET download_count = download_count + 1 WHERE id = ?");
            $updateStmt->execute([$cert_id]);
            
            // Generate PDF content (placeholder - replace with actual PDF generator)
            $pdfContent = "CERTIFICATE OF COMPLETION\n\n";
            $pdfContent .= "This certifies that\n";
            $pdfContent .= strtoupper($certificate['participant_name']) . "\n\n";
            $pdfContent .= "has successfully completed\n";
            $pdfContent .= $certificate['course_title'] . "\n\n";
            $pdfContent .= "Date: " . date('F j, Y', strtotime($certificate['generated_date'])) . "\n";
            $pdfContent .= "Certificate ID: CERT-" . str_pad($cert_id, 4, '0', STR_PAD_LEFT) . "\n";
            
            // Clear output buffer
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Send download headers
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="certificate_' . $cert_id . '.txt"');
            header('Content-Length: ' . strlen($pdfContent));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            echo $pdfContent;
            exit();
        } else {
            throw new Exception('Certificate not found');
        }
    } catch (Exception $e) {
        // Clear buffer and show error
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo "<script>alert('Download error: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit();
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['status' => 'error', 'message' => 'Unknown action'];
    
    try {
        switch ($action) {
            case 'email':
                $cert_id = intval($_POST['cert_id']);
                $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email address');
                }
                
                // Get certificate details
                $stmt = $pdo->prepare("SELECT c.*, cp.name as participant_name, cr.title as course_title 
                                      FROM certificates c 
                                      LEFT JOIN course_participants cp ON c.course_participant_id = cp.id 
                                      LEFT JOIN courses cr ON cp.course_id = cr.id 
                                      WHERE c.id = ?");
                $stmt->execute([$cert_id]);
                $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$certificate) {
                    throw new Exception('Certificate not found');
                }
                
                // Email content
                $subject = "Your Certificate - " . $certificate['course_title'];
                $message = "Dear " . $certificate['participant_name'] . ",\n\n";
                $message .= "Congratulations on completing " . $certificate['course_title'] . "!\n\n";
                $message .= "You can download your certificate using the following link:\n";
                $message .= "https://" . $_SERVER['HTTP_HOST'] . "/cursus-systeem/admin/certificates.php?action=download&cert_id=" . $cert_id . "\n\n";
                $message .= "Best regards,\nInventijn Team";
                
                // Send email
                $headers = "From: noreply@inventijn.nl\r\n";
                $headers .= "Reply-To: info@inventijn.nl\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                $emailSent = mail($email, $subject, $message, $headers);
                
                if ($emailSent) {
                    // Update email sent status
                    $updateStmt = $pdo->prepare("UPDATE certificates SET email_sent = 1 WHERE id = ?");
                    $updateStmt->execute([$cert_id]);
                    
                    $response = ['status' => 'success', 'message' => 'Email sent successfully to ' . $email];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to send email'];
                }
                break;
                
            case 'delete':
                $cert_id = intval($_POST['cert_id']);
                
                $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = ?");
                $success = $stmt->execute([$cert_id]);
                
                if ($success && $stmt->rowCount() > 0) {
                    $response = ['status' => 'success', 'message' => 'Certificate deleted successfully'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to delete certificate or certificate not found'];
                }
                break;
                
            case 'generate':
                $participant_id = intval($_POST['participant_id']);
                
                // Check if certificate already exists
                $checkStmt = $pdo->prepare("SELECT id FROM certificates WHERE course_participant_id = ?");
                $checkStmt->execute([$participant_id]);
                
                if ($checkStmt->fetch()) {
                    throw new Exception('Certificate already exists for this participant');
                }
                
                // Create new certificate
                $insertStmt = $pdo->prepare("INSERT INTO certificates (course_participant_id, generated_date, download_count, email_sent) VALUES (?, NOW(), 0, 0)");
                $success = $insertStmt->execute([$participant_id]);
                
                if ($success) {
                    $cert_id = $pdo->lastInsertId();
                    $response = ['status' => 'success', 'message' => 'Certificate generated successfully', 'cert_id' => $cert_id];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to generate certificate'];
                }
                break;
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
    
    // Clear output buffer and send JSON response
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// === DATA LOADING FOR PAGE DISPLAY ===

try {
    // Get all certificates with participant and course info
    $stmt = $pdo->query("
        SELECT c.*, 
               cp.name as participant_name, 
               cp.email as participant_email,
               cr.title as course_title,
               cr.id as course_id
        FROM certificates c
        LEFT JOIN course_participants cp ON c.course_participant_id = cp.id  
        LEFT JOIN courses cr ON cp.course_id = cr.id
        ORDER BY c.generated_date DESC
    ");
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $stats = [
        'total' => count($certificates),
        'generated' => count($certificates),
        'sent' => 0,
        'downloaded' => 0,
        'downloads' => 0
    ];
    
    foreach ($certificates as $cert) {
        if ($cert['email_sent']) $stats['sent']++;
        if ($cert['download_count'] > 0) $stats['downloaded']++;
        $stats['downloads'] += $cert['download_count'];
    }
    
    // Get participants without certificates for generation
    $participantsStmt = $pdo->query("
        SELECT cp.*, cr.title as course_title 
        FROM course_participants cp 
        LEFT JOIN courses cr ON cp.course_id = cr.id 
        LEFT JOIN certificates c ON cp.id = c.course_participant_id 
        WHERE c.id IS NULL AND cp.status = 'completed'
        ORDER BY cp.name
    ");
    $availableParticipants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $certificates = [];
    $availableParticipants = [];
    $stats = ['total' => 0, 'generated' => 0, 'sent' => 0, 'downloaded' => 0, 'downloads' => 0];
}

// End output buffering for HTML display
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificaten Beheer - Inventijn Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .certificates-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #667eea;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.5px;
        }
        
        .action-buttons {
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .certificates-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        .table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .cert-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875em;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .generation-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.25);
        }
    </style>
</head>
<body>
    <div class="certificates-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📜 Certificaten Beheer</h1>
            <p>Beheer en genereer cursuscertificaten voor deelnemers</p>
        </div>
        
        <!-- Error Display -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                ⚠️ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Totaal Certificaten</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['generated'] ?></div>
                <div class="stat-label">Gegenereerd</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['sent'] ?></div>
                <div class="stat-label">Verzonden</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['downloads'] ?></div>
                <div class="stat-label">Downloads</div>
            </div>
        </div>
        
        <!-- Certificate Generation Form -->
        <?php if (!empty($availableParticipants)): ?>
        <div class="generation-form">
            <h3>🎓 Nieuw Certificaat Genereren</h3>
            <p>Selecteer deelnemers die klaar zijn voor een certificaat:</p>
            
            <div class="form-group">
                <label class="form-label">Deelnemer:</label>
                <select id="participant-select" class="form-control">
                    <option value="">Selecteer deelnemer...</option>
                    <?php foreach ($availableParticipants as $participant): ?>
                        <option value="<?= $participant['id'] ?>">
                            <?= htmlspecialchars($participant['name']) ?> - <?= htmlspecialchars($participant['course_title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button onclick="generateCertificate()" class="btn btn-success">
                ✨ Genereer Certificaat
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Navigation -->
        <div class="action-buttons">
            <a href="dashboard.php" class="btn btn-secondary">← Terug naar Dashboard</a>
            <a href="courses.php" class="btn btn-primary">📚 Cursussen Beheer</a>
            <a href="participants.php" class="btn btn-primary">👥 Deelnemers Beheer</a>
        </div>
        
        <!-- Certificates Table -->
        <div class="certificates-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Certificaat #</th>
                        <th>Deelnemer</th>
                        <th>Cursus</th>
                        <th>Gegenereerd</th>
                        <th>Downloads</th>
                        <th>Status</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($certificates)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #6c757d;">
                                📄 Nog geen certificaten gegenereerd
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($certificates as $cert): ?>
                        <tr>
                            <td>
                                <strong>CERT-<?= str_pad($cert['id'], 4, '0', STR_PAD_LEFT) ?></strong>
                            </td>
                            <td>
                                <?= htmlspecialchars($cert['participant_name']) ?><br>
                                <small style="color: #6c757d;"><?= htmlspecialchars($cert['participant_email']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($cert['course_title']) ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($cert['generated_date'])) ?></td>
                            <td>
                                <strong><?= $cert['download_count'] ?>x</strong>
                            </td>
                            <td>
                                <?php if ($cert['email_sent']): ?>
                                    <span class="badge badge-success">✅ Verzonden</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">⏳ Niet verzonden</span>
                                <?php endif; ?>
                            </td>
                            <td class="cert-actions">
                                <a href="?action=download&cert_id=<?= $cert['id'] ?>" 
                                   class="btn btn-primary btn-sm" 
                                   title="Download certificaat">
                                    📥 Download
                                </a>
                                <button onclick="emailCertificate(<?= $cert['id'] ?>, '<?= htmlspecialchars($cert['participant_email']) ?>')" 
                                        class="btn btn-success btn-sm" 
                                        title="Verstuur via email">
                                    📧 Email
                                </button>
                                <button onclick="deleteCertificate(<?= $cert['id'] ?>)" 
                                        class="btn btn-danger btn-sm" 
                                        title="Verwijder certificaat">
                                    🗑️ Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Generate new certificate
        function generateCertificate() {
            const participantId = document.getElementById('participant-select').value;
            
            if (!participantId) {
                alert('Selecteer eerst een deelnemer');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'generate');
            formData.append('participant_id', participantId);
            
            fetch('certificates.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('✅ Certificaat succesvol gegenereerd!');
                    location.reload();
                } else {
                    alert('❌ Fout bij genereren: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Network error: ' + error.message);
            });
        }
        
        // Email certificate
        function emailCertificate(certId, defaultEmail) {
            let email = defaultEmail;
            
            if (!email) {
                email = prompt('Email adres:');
            } else {
                const confirmed = confirm('Certificaat versturen naar: ' + email + '?');
                if (!confirmed) {
                    email = prompt('Ander email adres:');
                }
            }
            
            if (!email) return;
            
            const formData = new FormData();
            formData.append('action', 'email');
            formData.append('cert_id', certId);
            formData.append('email', email);
            
            fetch('certificates.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Email fout: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Network error: ' + error.message);
            });
        }
        
        // Delete certificate
        function deleteCertificate(certId) {
            if (!confirm('Weet je zeker dat je dit certificaat wilt verwijderen?\n\nDit kan niet ongedaan gemaakt worden.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('cert_id', certId);
            
            fetch('certificates.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Delete fout: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Network error: ' + error.message);
            });
        }
        
        // Auto-refresh every 30 seconds for real-time updates
        setInterval(function() {
            // Only refresh if no modals or prompts are open
            if (!document.querySelector('.modal-open')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>