<?php
/**
 * Inventijn Deelnemersmanagement Systeem
 * Cursist Portal Interface
 * 
 * @version 2.2.0
 */

require_once 'includes/config.php';

session_start();

// Database connectie
$pdo = getDatabase();

// Check of gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Haal gebruiker en cursus informatie op
$stmt = $pdo->prepare("
    SELECT u.*, c.id as course_id, c.name as course_name, c.course_date, 
           c.location, c.time_range, c.access_start_time
    FROM users u 
    LEFT JOIN course_participants cp ON u.id = cp.user_id 
    LEFT JOIN courses c ON cp.course_id = c.id 
    WHERE u.id = ? AND u.active = 1
    ORDER BY c.course_date DESC LIMIT 1
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$currentCourse = null;
if ($user['course_id']) {
    $currentCourse = [
        'id' => $user['course_id'],
        'name' => $user['course_name'],
        'course_date' => $user['course_date'],
        'location' => $user['location'],
        'time_range' => $user['time_range'],
        'access_start_time' => $user['access_start_time']
    ];
}

// Check of cursus materiaal toegankelijk is
$materialsAccessible = false;
if ($currentCourse) {
    $accessTime = strtotime($currentCourse['access_start_time'] ?? $currentCourse['course_date'] . ' -1 day 18:00');
    $materialsAccessible = time() >= $accessTime;
}

// Haal materialen op als toegankelijk (placeholder - normaal uit database)
$materials = [];
if ($materialsAccessible && $currentCourse) {
    // Deze query zou normaal materialen ophalen uit course_materials tabel
    $materials = [
        [
            'id' => 1,
            'original_filename' => 'AI Booster - Handleiding.pdf',
            'description' => 'Complete handleiding voor de AI-Booster cursus',
            'file_type' => 'pdf',
            'file_size' => 2048000
        ],
        [
            'id' => 2,
            'original_filename' => 'Oefeningen en Cases.docx',
            'description' => 'Praktische oefeningen om thuis mee te werken',
            'file_type' => 'docx',
            'file_size' => 512000
        ]
    ];
}

// Logout functionaliteit
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursist Portal - <?= htmlspecialchars($user['name']) ?></title>
    <style>
        /* Import Inventijn brand fonts */
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@600&family=Barlow:wght@400;500;700&display=swap');
        
        :root {
            /* Inventijn Officiële Kleuren uit PDF */
            --inventijn-light-pink: #e3a1e5;
            --inventijn-purple: #b998e4;
            --inventijn-light-blue: #6b80e8;
            --inventijn-dark-blue: #3e5cc6;
            
            /* Behouden kleuren */
            --yellow: #F9CB40;
            --orange: #F9A03F;
            --white: #FFFFFF;
            --grey-light: #F2F2F2;
            
            /* Semantic mapping */
            --primary-color: var(--inventijn-dark-blue);
            --secondary-color: var(--inventijn-purple);
            --accent-color: var(--inventijn-light-blue);
            --highlight-color: var(--inventijn-light-pink);
            --text-dark: var(--inventijn-dark-blue);
            --text-medium: var(--inventijn-purple);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Barlow', sans-serif; 
            background: var(--grey-light);
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(62, 92, 198, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(249, 160, 63, 0.03) 0%, transparent 50%);
            min-height: 100vh;
        }
        
        /* Secure area header - Inventijn Donkerblauw */
        .secure-header {
            background: var(--inventijn-dark-blue);
            padding: 0.75rem 0;
            text-align: center;
            color: white;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: 0 2px 10px rgba(62, 92, 198, 0.3);
        }
        
        .secure-header::before {
            content: "🔒";
            margin-right: 0.5rem;
        }
        
        .header { 
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .header-content { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 2rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        
        .header-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-logo img {
            height: 32px;
            width: auto;
        }
        
        .header-logo .title {
            font-size: 1.25rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .user-info { 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
        }
        
        .user-avatar { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: linear-gradient(135deg, var(--orange) 0%, #e69500 100%); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: white; 
            font-weight: 600; 
            font-size: 0.875rem;
        }
        
        .user-details h3 { 
            color: var(--text-dark); 
            font-size: 0.875rem; 
            font-weight: 500;
        }
        
        .user-details p { 
            color: var(--text-medium); 
            font-size: 0.75rem; 
        }
        
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 2rem; 
        }
        
        .welcome-card { 
            background: white;
            border-radius: 16px; 
            padding: 2rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .welcome-card h1 { 
            color: var(--text-dark); 
            margin-bottom: 0.75rem; 
            font-size: 1.875rem; 
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            letter-spacing: -0.025em;
        }
        
        .welcome-card p { 
            color: var(--text-medium); 
            font-size: 1.125rem; 
            line-height: 1.6;
        }
        
        .course-info { 
            background: white;
            border-radius: 16px; 
            padding: 2rem; 
            margin-bottom: 2rem; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .course-title { 
            color: var(--text-dark); 
            font-size: 1.5rem; 
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            margin-bottom: 1.5rem;
            letter-spacing: -0.025em;
        }
        
        .course-details { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1rem; 
        }
        
        .detail-item { 
            padding: 1.25rem; 
            background: var(--grey-light); 
            border-radius: 12px; 
            border-left: 6px solid var(--yellow);
            transition: all 0.2s;
        }
        
        .detail-item:hover {
            background: #eaecf0;
            transform: translateY(-2px);
        }
        
        .detail-item h4 { 
            color: var(--text-medium); 
            margin-bottom: 0.5rem; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        
        .detail-item p { 
            color: var(--text-dark); 
            font-weight: 500; 
            font-size: 0.95rem;
        }
        
        .materials-section { 
            background: white;
            border-radius: 16px; 
            padding: 2rem; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }
        
        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .section-title { 
            color: var(--text-dark); 
            font-size: 1.5rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            letter-spacing: -0.025em;
        }
        
        .materials-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); 
            gap: 1.5rem; 
        }
        
        .material-card { 
            background: white; 
            border-radius: 12px; 
            padding: 1.5rem; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        
        .material-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            border-color: #d1d5db;
        }
        
        .material-icon { 
            width: 48px; 
            height: 48px; 
            border-radius: 8px; 
            background: linear-gradient(135deg, var(--orange) 0%, #e69500 100%); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .material-title { 
            color: var(--text-dark); 
            font-size: 1.125rem; 
            font-weight: 600; 
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }
        
        .material-description { 
            color: var(--text-medium); 
            font-size: 0.875rem; 
            margin-bottom: 1rem; 
            line-height: 1.5; 
        }
        
        .material-meta { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 1rem; 
        }
        
        .material-type { 
            background: #fff7e6; 
            color: var(--orange); 
            padding: 0.25rem 0.75rem; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            text-transform: uppercase;
            border: 1px solid var(--yellow);
        }
        
        .material-size { 
            color: #9ca3af; 
            font-size: 0.75rem; 
            font-weight: 500;
        }
        
        .btn { 
            background: linear-gradient(135deg, var(--orange) 0%, #e69500 100%); 
            color: white; 
            padding: 0.75rem 1.5rem; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 0.875rem; 
            font-weight: 600; 
            text-decoration: none; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
            color: white;
            box-shadow: 0 6px 20px rgba(249, 160, 63, 0.4);
        }
        
        /* Inventijn Roze voor gevaarlijke acties (was rood) */
        .btn-danger { 
            background: linear-gradient(135deg, var(--inventijn-light-pink) 0%, #d787d9 100%); 
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(227, 161, 229, 0.4);
        }
        
        .status-indicator { 
            padding: 0.5rem 1rem; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
        }
        
        .status-available { 
            background: #fff7e6; 
            color: var(--orange); 
            border: 1px solid var(--yellow);
        }
        
        .status-locked { 
            background: rgba(227, 161, 229, 0.1); 
            color: var(--inventijn-light-pink); 
            border: 1px solid var(--inventijn-light-pink);
        }
        
        .countdown { 
            background: linear-gradient(135deg, var(--yellow) 0%, #f4d03f 100%); 
            color: var(--text-dark); 
            padding: 1.5rem; 
            border-radius: 12px; 
            text-align: center; 
            margin-bottom: 1rem;
            border: 1px solid var(--orange);
        }
        
        .countdown h3 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .footer-info {
            text-align: center;
            margin-top: 3rem;
            padding: 2rem;
            background: white;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
        }
        
        .footer-info p {
            color: var(--text-medium);
            margin-bottom: 0.5rem;
        }
        
        .footer-info a {
            color: var(--orange);
            text-decoration: none;
            font-weight: 500;
        }
        
        .footer-info a:hover {
            text-decoration: underline;
        }
        
        .alert-error { 
            background: rgba(227, 161, 229, 0.1); 
            color: var(--inventijn-light-pink); 
            border: 1px solid var(--inventijn-light-pink);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .header-content { padding: 0 1rem; }
            .course-details { grid-template-columns: 1fr; }
            .materials-grid { grid-template-columns: 1fr; }
            .user-info .user-details { display: none; }
            .header-logo .title { font-size: 1rem; }
        }
    </style>
</head>
<body>
    <!-- Secure area indicator - Inventijn Donkerblauw -->
    <div class="secure-header">
        Beveiligd Cursist Gebied - Jouw Persoonlijke Leeromgeving
    </div>

    <header class="header">
        <div class="header-content">
            <div class="header-logo">
                <img src="https://inventijn.nl/assets/images/logo.svg" alt="Inventijn Logo">
                <div class="title">Cursistenportaal</div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div class="user-details">
                    <h3><?= htmlspecialchars($user['name']) ?></h3>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <a href="?logout=1" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                    Uitloggen
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <h1>👋 Welkom, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h1>
            <p>Welkom bij je persoonlijke cursusomgeving. Hier vind je al het materiaal en de informatie voor je cursussen.</p>
        </div>

        <?php if ($currentCourse): ?>
        <!-- Course Information -->
        <div class="course-info">
            <h2 class="course-title">📚 <?= htmlspecialchars($currentCourse['name']) ?></h2>
            
            <div class="course-details">
                <div class="detail-item">
                    <h4>📅 Datum & Tijd</h4>
                    <p><?= date('d F Y', strtotime($currentCourse['course_date'])) ?></p>
                    <p><?= $currentCourse['time_range'] ?? date('H:i', strtotime($currentCourse['course_date'])) ?></p>
                </div>
                
                <div class="detail-item">
                    <h4>📍 Locatie</h4>
                    <p><?= htmlspecialchars($currentCourse['location'] ?? 'Wordt nog bekendgemaakt') ?></p>
                </div>
                
                <div class="detail-item">
                    <h4>✅ Status</h4>
                    <p><span class="status-indicator status-available">Ingeschreven</span></p>
                </div>
                
                <div class="detail-item">
                    <h4>🎯 Materiaal Status</h4>
                    <p>
                        <?php if ($materialsAccessible): ?>
                            <span class="status-indicator status-available">Beschikbaar</span>
                        <?php else: ?>
                            <span class="status-indicator status-locked">Vergrendeld</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Materials Section -->
        <div class="materials-section">
            <div class="section-header">
                <h2 class="section-title">📎 Cursusmateriaal</h2>
                <?php if ($materialsAccessible): ?>
                    <span class="status-indicator status-available">✅ Toegankelijk</span>
                <?php else: ?>
                    <span class="status-indicator status-locked">🔒 Vergrendeld</span>
                <?php endif; ?>
            </div>
            
            <?php if (!$materialsAccessible): ?>
                <div class="countdown">
                    <h3>⏰ Materiaal wordt beschikbaar</h3>
                    <p>Het cursusmateriaal wordt beschikbaar gemaakt de avond voor de cursus om 18:00 uur.</p>
                    <p><strong>Beschikbaar vanaf: <?= date('d F Y \o\m H:i', strtotime($currentCourse['access_start_time'] ?? $currentCourse['course_date'] . ' -1 day 18:00')) ?></strong></p>
                </div>
            <?php elseif (empty($materials)): ?>
                <div style="text-align: center; padding: 3rem; color: #6b7280;">
                    <h3>📭 Nog geen materiaal beschikbaar</h3>
                    <p>De docent heeft nog geen materiaal geüpload voor deze cursus.</p>
                </div>
            <?php else: ?>
                <div class="materials-grid">
                    <?php foreach ($materials as $material): ?>
                    <div class="material-card">
                        <div class="material-icon">
                            <?php
                            $extension = strtolower($material['file_type']);
                            $icon = '📄';
                            if ($extension === 'pdf') $icon = '📕';
                            elseif (in_array($extension, ['doc', 'docx'])) $icon = '📝';
                            elseif (in_array($extension, ['xls', 'xlsx'])) $icon = '📊';
                            elseif (in_array($extension, ['ppt', 'pptx'])) $icon = '📈';
                            echo $icon;
                            ?>
                        </div>
                        
                        <h3 class="material-title"><?= htmlspecialchars($material['original_filename']) ?></h3>
                        
                        <?php if ($material['description']): ?>
                        <p class="material-description"><?= htmlspecialchars($material['description']) ?></p>
                        <?php endif; ?>
                        
                        <div class="material-meta">
                            <span class="material-type"><?= strtoupper($material['file_type']) ?></span>
                            <span class="material-size"><?= number_format($material['file_size'] / 1024, 0) ?> KB</span>
                        </div>
                        
                        <a href="#" class="btn" style="width: 100%; justify-content: center;" onclick="alert('Download functionaliteit wordt geïmplementeerd na volledige setup.')">
                            📥 Download
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- No Course Found -->
        <div class="course-info">
            <h2 style="color: var(--inventijn-light-pink);">⚠️ Geen actieve cursus gevonden</h2>
            <p>Er is momenteel geen actieve cursus gekoppeld aan je account. Neem contact op met de organisatie als je denkt dat dit een fout is.</p>
            <br>
            <a href="mailto:<?= ADMIN_EMAIL ?>" class="btn">📧 Contact Opnemen</a>
        </div>
        <?php endif; ?>
        
        <!-- Footer Info -->
        <div class="footer-info">
            <p>💡 Heb je vragen over de cursus? Mail naar <a href="mailto:<?= ADMIN_EMAIL ?>"><?= ADMIN_EMAIL ?></a></p>
            <p>🔒 Je persoonlijke toegang is beveiligd en gekoppeld aan je e-mailadres</p>
            <p style="margin-top: 1rem; font-size: 0.75rem; color: #9ca3af;">
                Powered by Inventijn Learning Platform v2.2.0
            </p>
        </div>
    </div>
</body>
</html>