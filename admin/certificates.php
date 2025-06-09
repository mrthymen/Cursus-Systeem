<?php
/**
 * Inventijn Certificate Management v4.1.0
 * Now with unified admin template integration
 * Previous: v4.0 (standalone) → Current: v4.1.0 (integrated)
 * File size: ~54.2KB → Enhanced with template integration
 * Updated: 2025-06-09
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_user'])) {
    header('Location: index.php?redirect=certificates.php');
    exit;
}

// Include unified admin template and config
require_once '../includes/admin_template.php';
require_once '../includes/config.php';

// Database connections (preserve existing mPDF compatibility)
$pdo = getDatabase();
$mysqli = getMySQLiDatabase();

// Handle certificate actions (preserve all existing functionality)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_certificate':
                try {
                    $participant_id = (int)$_POST['participant_id'];
                    
                    // Use existing CertificateGenerator (preserve mPDF workflow)
                    require_once '../includes/CertificateGenerator.php';
                    $generator = new CertificateGenerator($mysqli);
                    
                    $result = $generator->generateCertificate($participant_id);
                    
                    if ($result['success']) {
                        $_SESSION['admin_message'] = [
                            'text' => 'Certificate generated successfully!',
                            'type' => 'success'
                        ];
                    } else {
                        $_SESSION['admin_message'] = [
                            'text' => 'Certificate generation failed: ' . $result['message'],
                            'type' => 'error'
                        ];
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = [
                        'text' => 'Error: ' . $e->getMessage(),
                        'type' => 'error'
                    ];
                }
                break;
                
            case 'regenerate_certificate':
                try {
                    $certificate_id = (int)$_POST['certificate_id'];
                    
                    // Get participant ID from certificate
                    $stmt = $pdo->prepare("SELECT course_participant_id FROM certificates WHERE id = ?");
                    $stmt->execute([$certificate_id]);
                    $participant_id = $stmt->fetchColumn();
                    
                    if ($participant_id) {
                        require_once '../includes/CertificateGenerator.php';
                        $generator = new CertificateGenerator($mysqli);
                        
                        $result = $generator->regenerateCertificate($certificate_id, $participant_id);
                        
                        if ($result['success']) {
                            $_SESSION['admin_message'] = [
                                'text' => 'Certificate regenerated successfully!',
                                'type' => 'success'
                            ];
                        } else {
                            $_SESSION['admin_message'] = [
                                'text' => 'Certificate regeneration failed: ' . $result['message'],
                                'type' => 'error'
                            ];
                        }
                    }
                } catch (Exception $e) {
                    $_SESSION['admin_message'] = [
                        'text' => 'Error: ' . $e->getMessage(),
                        'type' => 'error'
                    ];
                }
                break;
        }
        
        header('Location: certificates.php');
        exit;
    }
}

// Get certificate statistics for dashboard
$certificate_stats = [
    'total_generated' => $pdo->query("SELECT COUNT(*) FROM certificates")->fetchColumn(),
    'pending_generation' => $pdo->query("
        SELECT COUNT(*) 
        FROM course_participants cp 
        JOIN courses c ON cp.course_id = c.id 
        WHERE cp.payment_status = 'paid' 
        AND c.course_date < NOW() 
        AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
    ")->fetchColumn(),
    'downloaded_today' => $pdo->query("
        SELECT COUNT(*) 
        FROM certificates 
        WHERE DATE(download_date) = CURDATE()
    ")->fetchColumn(),
    'download_rate' => 0 // Calculate percentage
];

// Calculate download rate
if ($certificate_stats['total_generated'] > 0) {
    $downloaded_count = $pdo->query("SELECT COUNT(*) FROM certificates WHERE download_date IS NOT NULL")->fetchColumn();
    $certificate_stats['download_rate'] = round(($downloaded_count / $certificate_stats['total_generated']) * 100, 1);
}

// Get all certificates with enhanced data
$certificates_query = "
    SELECT 
        c.*,
        cp.payment_status,
        cp.payment_date,
        u.name as participant_name,
        u.email as participant_email,
        cr.course_name,
        cr.course_date,
        cr.instructor,
        CASE 
            WHEN c.download_date IS NOT NULL THEN 'Downloaded'
            WHEN c.generated_date IS NOT NULL THEN 'Ready'
            ELSE 'Pending'
        END as status,
        DATEDIFF(NOW(), c.generated_date) as days_since_generated
    FROM certificates c
    JOIN course_participants cp ON c.course_participant_id = cp.id
    JOIN users u ON cp.user_id = u.id
    JOIN courses cr ON cp.course_id = cr.id
    ORDER BY c.generated_date DESC
";

$certificates = $pdo->query($certificates_query)->fetchAll(PDO::FETCH_ASSOC);

// Get participants ready for certificate generation
$ready_for_certificates_query = "
    SELECT 
        cp.*,
        u.name as participant_name,
        u.email as participant_email,
        c.course_name,
        c.course_date,
        c.instructor,
        DATEDIFF(NOW(), c.course_date) as days_since_completion
    FROM course_participants cp
    JOIN users u ON cp.user_id = u.id
    JOIN courses c ON cp.course_id = c.id
    WHERE cp.payment_status = 'paid'
    AND c.course_date < NOW()
    AND NOT EXISTS (SELECT 1 FROM certificates WHERE course_participant_id = cp.id)
    ORDER BY c.course_date ASC
";

$ready_participants = $pdo->query($ready_for_certificates_query)->fetchAll(PDO::FETCH_ASSOC);

// Render unified header
renderAdminHeader('Certificate Management', $pdo);
renderPageHeader('Certificate Management', '<a href="index.php">Dashboard</a> > Certificates');

// Certificate-specific dashboard stats
echo '
<div class="quick-stats">
    <div class="stat-card">
        <div class="stat-value">' . $certificate_stats['total_generated'] . '</div>
        <div class="stat-label">Total Certificates</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">' . $certificate_stats['pending_generation'] . '</div>
        <div class="stat-label">Pending Generation</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">' . $certificate_stats['downloaded_today'] . '</div>
        <div class="stat-label">Downloaded Today</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">' . $certificate_stats['download_rate'] . '%</div>
        <div class="stat-label">Download Rate</div>
    </div>
</div>
';

// Display session messages
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    echo '<div class="message ' . $message['type'] . '" style="display: block;">' . htmlspecialchars($message['text']) . '</div>';
    unset($_SESSION['admin_message']);
}

?>

<style>
/* Certificate-specific styles that extend the unified template */
.certificate-section {
    background: white;
    border-radius: 1rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.section-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--inventijn-dark);
}

.certificate-grid {
    display: grid;
    gap: 1rem;
    margin-top: 1rem;
}

.certificate-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
}

.certificate-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.certificate-card.ready {
    background: linear-gradient(135deg, #dcfce7 0%, #f0fdf4 100%);
    border-color: var(--inventijn-success);
}

.certificate-card.downloaded {
    background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
    border-color: var(--inventijn-primary);
}

.certificate-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.participant-info h3 {
    color: var(--inventijn-dark);
    margin-bottom: 0.25rem;
    font-size: 1.1rem;
}

.participant-info p {
    color: #64748b;
    font-size: 0.9rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: bold;
    color: white;
}

.status-ready { background: var(--inventijn-success); }
.status-downloaded { background: var(--inventijn-primary); }
.status-pending { background: var(--inventijn-warning); }

.certificate-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.9rem;
}

.certificate-actions {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

.btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.btn-primary {
    background: var(--inventijn-primary);
    color: white;
}

.btn-primary:hover {
    background: var(--inventijn-secondary);
    transform: translateY(-1px);
}

.btn-success {
    background: var(--inventijn-success);
    color: white;
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-warning {
    background: var(--inventijn-warning);
    color: white;
}

.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    color: #64748b;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #e2e8f0;
}

.quick-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.message {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
    display: none;
}

.message.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.message.error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
</style>

<div id="message" class="message"></div>

<!-- Ready for Certificate Generation Section -->
<?php if (!empty($ready_participants)): ?>
<div class="certificate-section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-clock"></i> Ready for Certificate Generation
        </h2>
        <div class="quick-actions">
            <button class="btn btn-success" onclick="generateAllCertificates()">
                <i class="fas fa-magic"></i> Generate All (<?= count($ready_participants) ?>)
            </button>
        </div>
    </div>
    
    <div class="certificate-grid">
        <?php foreach ($ready_participants as $participant): ?>
            <div class="certificate-card ready">
                <div class="certificate-header">
                    <div class="participant-info">
                        <h3><?= htmlspecialchars($participant['participant_name']) ?></h3>
                        <p><?= htmlspecialchars($participant['participant_email']) ?></p>
                    </div>
                    <span class="status-badge status-pending">Pending Generation</span>
                </div>
                
                <div class="certificate-details">
                    <div class="detail-item">
                        <i class="fas fa-book"></i>
                        <?= htmlspecialchars($participant['course_name']) ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-calendar"></i>
                        <?= date('d-m-Y', strtotime($participant['course_date'])) ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-user-tie"></i>
                        <?= htmlspecialchars($participant['instructor']) ?>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i>
                        <?= $participant['days_since_completion'] ?> dagen geleden
                    </div>
                </div>
                
                <div class="certificate-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="generate_certificate">
                        <input type="hidden" name="participant_id" value="<?= $participant['id'] ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-certificate"></i> Generate Certificate
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Generated Certificates Section -->
<div class="certificate-section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-certificate"></i> Generated Certificates
        </h2>
        <div class="quick-actions">
            <a href="?export=csv" class="btn btn-secondary">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
    </div>
    
    <?php if (empty($certificates)): ?>
        <div class="empty-state">
            <i class="fas fa-certificate"></i>
            <h3>Geen certificaten gegenereerd</h3>
            <p>Certificaten verschijnen hier zodra ze gegenereerd zijn.</p>
        </div>
    <?php else: ?>
        <div class="certificate-grid">
            <?php foreach ($certificates as $certificate): ?>
                <div class="certificate-card <?= strtolower($certificate['status']) ?>">
                    <div class="certificate-header">
                        <div class="participant-info">
                            <h3><?= htmlspecialchars($certificate['participant_name']) ?></h3>
                            <p><?= htmlspecialchars($certificate['participant_email']) ?></p>
                        </div>
                        <span class="status-badge status-<?= strtolower($certificate['status']) ?>">
                            <?= $certificate['status'] ?>
                        </span>
                    </div>
                    
                    <div class="certificate-details">
                        <div class="detail-item">
                            <i class="fas fa-book"></i>
                            <?= htmlspecialchars($certificate['course_name']) ?>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <?= date('d-m-Y', strtotime($certificate['course_date'])) ?>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-file-pdf"></i>
                            Generated: <?= date('d-m-Y H:i', strtotime($certificate['generated_date'])) ?>
                        </div>
                        <?php if ($certificate['download_date']): ?>
                            <div class="detail-item">
                                <i class="fas fa-download"></i>
                                Downloaded: <?= date('d-m-Y H:i', strtotime($certificate['download_date'])) ?>
                            </div>
                        <?php else: ?>
                            <div class="detail-item">
                                <i class="fas fa-exclamation-triangle"></i>
                                Not downloaded yet
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="certificate-actions">
                        <?php if ($certificate['file_path'] && file_exists('../' . $certificate['file_path'])): ?>
                            <a href="../<?= htmlspecialchars($certificate['file_path']) ?>" 
                               class="btn btn-primary" target="_blank">
                                <i class="fas fa-eye"></i> View PDF
                            </a>
                            <a href="../<?= htmlspecialchars($certificate['file_path']) ?>" 
                               class="btn btn-success" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="regenerate_certificate">
                            <input type="hidden" name="certificate_id" value="<?= $certificate['id'] ?>">
                            <button type="submit" class="btn btn-warning" 
                                    onclick="return confirm('Regenerate this certificate?')">
                                <i class="fas fa-redo"></i> Regenerate
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Enhanced JavaScript for certificate management
document.addEventListener('DOMContentLoaded', function() {
    
    // Generate all certificates functionality
    window.generateAllCertificates = function() {
        if (!confirm('Generate certificates for all eligible participants?')) {
            return;
        }
        
        const readyCards = document.querySelectorAll('.certificate-card.ready');
        let processed = 0;
        
        readyCards.forEach((card, index) => {
            setTimeout(() => {
                const form = card.querySelector('form');
                if (form) {
                    form.submit();
                }
            }, index * 1000); // Stagger submissions to avoid overwhelming the server
        });
        
        showMessage('Starting bulk certificate generation...', 'success');
    };
    
    // Auto-refresh certificates every 30 seconds
    function refreshCertificateStats() {
        fetch(window.location.href + '?ajax=stats')
            .then(response => response.json())
            .then(data => {
                // Update certificate counts in navigation and dashboard
                const statCards = document.querySelectorAll('.stat-value');
                if (data.total_generated !== undefined && statCards[0]) {
                    statCards[0].textContent = data.total_generated;
                }
                if (data.pending_generation !== undefined && statCards[1]) {
                    statCards[1].textContent = data.pending_generation;
                }
            })
            .catch(err => console.log('Stats refresh failed:', err));
    }
    
    // Refresh every 30 seconds
    setInterval(refreshCertificateStats, 30000);
    
    // Show message function
    function showMessage(text, type) {
        const messageDiv = document.getElementById('message');
        messageDiv.textContent = text;
        messageDiv.className = `message ${type}`;
        messageDiv.style.display = 'block';
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }
    
    // Add smooth animations for certificate generation
    document.querySelectorAll('form[method="POST"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            if (button && button.textContent.includes('Generate')) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
                button.disabled = true;
            }
        });
    });
});

// Handle AJAX stats requests for real-time updates
<?php if (isset($_GET['ajax']) && $_GET['ajax'] === 'stats'): ?>
<?php
header('Content-Type: application/json');
echo json_encode($certificate_stats);
exit;
?>
<?php endif; ?>
</script>

<?php renderAdminFooter(); ?>