<?php
/**
 * Certificates.php Fixed Structure v4.1.0
 * Inventijn Cursus Systeem
 * 
 * Fix voor header conflicts - actions VOOR HTML output
 */

// === PHASE 1: CONFIGURATION & SETUP ===
require_once('../includes/config.php');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering om ongewenste output te vangen
ob_start();

// === PHASE 2: ACTION DETECTION ===
$isAction = (isset($_POST['action']) || isset($_GET['action']) || isset($_GET['download']));
$actionType = $_POST['action'] ?? $_GET['action'] ?? ($_GET['download'] ? 'download' : null);

// === PHASE 3: ACTION PROCESSING (VOOR HTML HEADER) ===
if ($isAction) {
    // Clean any accidentally buffered output
    ob_clean();
    
    switch ($actionType) {
        case 'download':
            // === PDF DOWNLOAD HANDLER ===
            $cert_id = $_GET['cert_id'] ?? $_POST['cert_id'];
            
            try {
                $stmt = $pdo->prepare("SELECT * FROM certificates WHERE id = ?");
                $stmt->execute([$cert_id]);
                $certificate = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$certificate) {
                    throw new Exception("Certificate not found");
                }
                
                // Generate PDF content
                $pdfGenerator = new CertificateGenerator($pdo, getMySQLiDatabase());
                $pdfContent = $pdfGenerator->generateCertificate($cert_id);
                
                // Clean output buffer completely
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                // Send PDF headers and content
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="certificate_' . $cert_id . '.pdf"');
                header('Content-Length: ' . strlen($pdfContent));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                echo $pdfContent;
                exit(); // CRITICAL: Stop all further processing
                
            } catch (Exception $e) {
                // Error handling - show user-friendly message
                header('Content-Type: text/html');
                echo "<script>alert('Download failed: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
                exit();
            }
            break;
            
        case 'email':
            // === EMAIL HANDLER ===
            $cert_id = $_POST['cert_id'];
            $email = $_POST['email'];
            
            try {
                // Email logic here
                $emailSent = mail($email, "Your Certificate", "Certificate attached");
                
                // Clean JSON response
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => $emailSent ? 'success' : 'error',
                    'message' => $emailSent ? 'Email sent successfully' : 'Email failed to send'
                ]);
                exit();
                
            } catch (Exception $e) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
                exit();
            }
            break;
            
        case 'delete':
            // === DELETE HANDLER ===
            $cert_id = $_POST['cert_id'];
            
            try {
                $stmt = $pdo->prepare("DELETE FROM certificates WHERE id = ?");
                $success = $stmt->execute([$cert_id]);
                
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => $success ? 'success' : 'error',
                    'message' => $success ? 'Certificate deleted' : 'Delete failed'
                ]);
                exit();
                
            } catch (Exception $e) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ]);
                exit();
            }
            break;
            
        case 'generate':
            // === GENERATE NEW CERTIFICATE ===
            $participant_id = $_POST['participant_id'];
            
            try {
                $pdfGenerator = new CertificateGenerator($pdo, getMySQLiDatabase());
                $cert_id = $pdfGenerator->createCertificateRecord($participant_id);
                
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Certificate generated',
                    'cert_id' => $cert_id
                ]);
                exit();
                
            } catch (Exception $e) {
                while (ob_get_level()) {
                    ob_end_clean();
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error', 
                    'message' => $e->getMessage()
                ]);
                exit();
            }
            break;
    }
}

// === PHASE 4: SESSION CHECK (NA actions) ===
session_start();
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit();
}

// === PHASE 5: DATA LOADING ===
try {
    // Get certificates data
    $stmt = $pdo->query("
        SELECT c.*, cp.name as participant_name, cr.title as course_title,
               cp.email as participant_email
        FROM certificates c
        LEFT JOIN course_participants cp ON c.course_participant_id = cp.id  
        LEFT JOIN courses cr ON cp.course_id = cr.id
        ORDER BY c.generated_date DESC
    ");
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats for dashboard
    $stats = [
        'total' => count($certificates),
        'generated_today' => 0,
        'downloaded' => 0,
        'sent' => 0
    ];
    
    foreach ($certificates as $cert) {
        if (date('Y-m-d') === date('Y-m-d', strtotime($cert['generated_date']))) {
            $stats['generated_today']++;
        }
        if ($cert['download_count'] > 0) {
            $stats['downloaded']++;
        }
        if ($cert['email_sent']) {
            $stats['sent']++;
        }
    }
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $certificates = [];
    $stats = ['total' => 0, 'generated_today' => 0, 'downloaded' => 0, 'sent' => 0];
}

// === PHASE 6: HTML OUTPUT (NA alle processing) ===
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificaten Beheer - Inventijn Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .cert-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn-download {
            background: var(--inventijn-primary);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-email {
            background: var(--inventijn-secondary);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-delete {
            background: var(--inventijn-danger);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: var(--inventijn-primary);
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Unified Navigation Header -->
        <?php include('admin_header.php'); // Nu VEILIG om te includen ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>📜 Certificaten Beheer</h1>
                <p>Genereer, beheer en verstuur cursuscertificaten</p>
            </div>
            
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
                    <div class="stat-number"><?= $stats['generated_today'] ?></div>
                    <div class="stat-label">Vandaag Gegenereerd</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['downloaded'] ?></div>
                    <div class="stat-label">Gedownload</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['sent'] ?></div>
                    <div class="stat-label">Verstuurd</div>
                </div>
            </div>
            
            <!-- Certificates Table -->
            <div class="data-table-container">
                <table class="data-table">
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
                        <?php foreach ($certificates as $cert): ?>
                        <tr>
                            <td>CERT-<?= str_pad($cert['id'], 4, '0', STR_PAD_LEFT) ?></td>
                            <td><?= htmlspecialchars($cert['participant_name']) ?></td>
                            <td><?= htmlspecialchars($cert['course_title']) ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($cert['generated_date'])) ?></td>
                            <td><?= $cert['download_count'] ?>x</td>
                            <td>
                                <?php if ($cert['email_sent']): ?>
                                    <span class="badge badge-success">Verstuurd</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Niet verstuurd</span>
                                <?php endif; ?>
                            </td>
                            <td class="cert-actions">
                                <a href="?action=download&cert_id=<?= $cert['id'] ?>" 
                                   class="btn-download" title="Download PDF">
                                    📥 Download
                                </a>
                                <button onclick="emailCertificate(<?= $cert['id'] ?>, '<?= htmlspecialchars($cert['participant_email']) ?>')" 
                                        class="btn-email" title="Verstuur via email">
                                    📧 Email
                                </button>
                                <button onclick="deleteCertificate(<?= $cert['id'] ?>)" 
                                        class="btn-delete" title="Verwijder certificaat">
                                    🗑️ Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <script>
    // Email certificate function
    function emailCertificate(certId, email) {
        if (!email) {
            email = prompt('Email adres:');
            if (!email) return;
        }
        
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
                alert('✅ Email verzonden naar ' + email);
                location.reload();
            } else {
                alert('❌ Email fout: ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Network error: ' + error.message);
        });
    }
    
    // Delete certificate function
    function deleteCertificate(certId) {
        if (!confirm('Weet je zeker dat je dit certificaat wilt verwijderen?')) {
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
                alert('✅ Certificaat verwijderd');
                location.reload();
            } else {
                alert('❌ Delete fout: ' + data.message);
            }
        })
        .catch(error => {
            alert('❌ Network error: ' + error.message);
        });
    }
    </script>
</body>
</html>

<?php
// End output buffering and flush
ob_end_flush();
?>