<?php
/**
 * Inventijn Deelnemersmanagement Systeem
 * Cursist Login Pagina
 * 
 * @version 2.2.0
 */

require_once 'includes/config.php';

session_start();

// Database connectie
$pdo = getDatabase();

$error = '';

// Handle login vanuit email link (backward compatibility)
if (isset($_GET['access']) && !empty($_GET['access'])) {
    $accessKey = trim($_GET['access']);
    
    // Zoek gebruiker met deze access key
    $stmt = $pdo->prepare("
        SELECT u.*, c.id as course_id, c.name as course_name, c.course_date, c.access_start_time
        FROM users u 
        LEFT JOIN course_participants cp ON u.id = cp.user_id 
        LEFT JOIN courses c ON cp.course_id = c.id 
        WHERE u.access_key = ? AND u.active = 1
        ORDER BY c.course_date DESC LIMIT 1
    ");
    $stmt->execute([$accessKey]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Check of toegang al beschikbaar is
        $accessTime = strtotime($user['access_start_time'] ?? $user['course_date'] . ' -1 day 18:00');
        
        if (time() >= $accessTime) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['course_id'] = $user['course_id'];
            header("Location: index.php");
            exit;
        } else {
            $error = "De cursusmateriaal is nog niet beschikbaar. Probeer het vanaf " . 
                     date('d F Y \o\m H:i', $accessTime) . ".";
        }
    } else {
        $error = "Ongeldige of verlopen toegangslink.";
    }
}

// Handle manual login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $accessKey = trim($_POST['access_key'] ?? '');
    
    if ($email && $accessKey) {
        $stmt = $pdo->prepare("
            SELECT u.*, c.id as course_id, c.name as course_name, c.course_date, c.access_start_time
            FROM users u 
            LEFT JOIN course_participants cp ON u.id = cp.user_id 
            LEFT JOIN courses c ON cp.course_id = c.id 
            WHERE u.email = ? AND u.access_key = ? AND u.active = 1
            ORDER BY c.course_date DESC LIMIT 1
        ");
        $stmt->execute([$email, $accessKey]);
        $user = $stmt->fetch();
        
        if ($user) {
            $accessTime = strtotime($user['access_start_time'] ?? $user['course_date'] . ' -1 day 18:00');
            
            if (time() >= $accessTime) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['course_id'] = $user['course_id'];
                header("Location: index.php");
                exit;
            } else {
                $error = "De cursusmateriaal is nog niet beschikbaar. Probeer het vanaf " . 
                         date('d F Y \o\m H:i', $accessTime) . ".";
            }
        } else {
            $error = "Ongeldige combinatie van e-mailadres en toegangscode.";
        }
    } else {
        $error = "Vul beide velden in.";
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inloggen - Inventijn Cursistenportaal</title>
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
            --text-dark: var(--inventijn-dark-blue);
            --text-medium: var(--inventijn-purple);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Barlow', sans-serif; 
            background: var(--grey-light);
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(62, 92, 198, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(249, 160, 63, 0.05) 0%, transparent 50%);
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            padding: 1rem;
        }
        
        /* Secure area indicator - Inventijn Donkerblauw */
        .secure-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: var(--inventijn-dark-blue);
            padding: 0.5rem;
            text-align: center;
            color: white;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(62, 92, 198, 0.3);
        }
        
        .secure-header::before {
            content: "🔒";
            margin-right: 0.5rem;
        }
        
        .login-container { 
            background: white;
            backdrop-filter: blur(20px); 
            padding: 3rem; 
            border-radius: 16px; 
            box-shadow: 
                0 20px 25px -5px rgba(0, 0, 0, 0.1),
                0 10px 10px -5px rgba(0, 0, 0, 0.04);
            max-width: 480px; 
            width: 100%;
            border: 1px solid rgba(255,255,255,0.2);
            margin-top: 2rem;
        }
        
        .logo-section { 
            text-align: center; 
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .logo-section img {
            height: 48px;
            width: auto;
            margin-bottom: 1rem;
        }
        
        .logo-section h1 { 
            color: var(--text-dark); 
            font-size: 1.5rem; 
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }
        
        .logo-section .subtitle { 
            color: var(--text-medium); 
            font-size: 0.95rem;
            font-weight: 400;
        }
        
        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #fff7e6;
            color: var(--orange);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 1rem;
            border: 1px solid var(--yellow);
        }
        
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 0.75rem; 
            color: var(--text-medium); 
            font-weight: 500; 
            font-size: 0.875rem;
        }
        
        .form-group input { 
            width: 100%; 
            padding: 1rem 1rem; 
            border: 1.5px solid #d1d5db; 
            border-radius: 8px; 
            font-size: 1rem; 
            transition: all 0.2s; 
            background: #ffffff;
            font-family: inherit;
        }
        
        .form-group input:focus { 
            outline: none; 
            border-color: var(--orange); 
            box-shadow: 0 0 0 3px rgba(249, 160, 63, 0.1);
        }
        
        .btn { 
            background: linear-gradient(135deg, var(--orange) 0%, #e69500 100%); 
            color: white; 
            padding: 1rem 2rem; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 1rem; 
            font-weight: 600; 
            width: 100%; 
            transition: all 0.2s;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 20px rgba(249, 160, 63, 0.4);
        }
        
        .alert { 
            padding: 1rem; 
            border-radius: 8px; 
            margin-bottom: 1.5rem; 
            border: 1px solid;
            font-size: 0.875rem;
        }
        
        .alert-error { 
            background: rgba(227, 161, 229, 0.1); 
            color: var(--inventijn-light-pink); 
            border-color: var(--inventijn-light-pink); 
        }
        
        .help-section { 
            background: var(--grey-light); 
            border: 1px solid #e2e8f0; 
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-top: 2rem;
        }
        
        .help-section h3 { 
            color: var(--text-dark); 
            margin-bottom: 1rem; 
            font-size: 1rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
        }
        
        .help-section ul { 
            color: var(--text-medium); 
            line-height: 1.6;
            padding-left: 1.25rem;
        }
        
        .help-section li { 
            margin-bottom: 0.5rem; 
            font-size: 0.875rem;
        }
        
        .contact-section { 
            text-align: center; 
            margin-top: 2rem; 
            padding-top: 1.5rem; 
            border-top: 1px solid #e5e7eb;
        }
        
        .contact-section p { 
            color: var(--text-medium); 
            font-size: 0.875rem; 
        }
        
        .contact-section a { 
            color: var(--orange); 
            text-decoration: none; 
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .contact-section a:hover { 
            color: #e69500;
            text-decoration: underline; 
        }
        
        .footer-branding {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 0.75rem;
        }
        
        @media (max-width: 640px) {
            .login-container { 
                padding: 2rem; 
                margin: 1rem;
            }
            .logo-section h1 { 
                font-size: 1.25rem; 
            }
        }
    </style>
</head>
<body>
    <!-- Secure area indicator - Inventijn Donkerblauw -->
    <div class="secure-header">
        Beveiligd Cursist Gebied - Inventijn Cursistenportaal
    </div>

    <div class="login-container">
        <div class="logo-section">
            <img src="https://inventijn.nl/assets/images/logo.svg" alt="Inventijn Logo">
            <h1>Cursistenportaal</h1>
            <p class="subtitle">Toegang tot je cursusmateriaal</p>
            <div class="secure-badge">
                🔒 Beveiligd Gebied
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ Toegang geweigerd:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="email">📧 E-mailadres</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="jouw@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required 
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="access_key">🔑 Persoonlijke Toegangscode</label>
                <input 
                    type="text" 
                    id="access_key" 
                    name="access_key" 
                    placeholder="Je unieke toegangscode uit de e-mail"
                    value="<?= htmlspecialchars($_POST['access_key'] ?? $_GET['access'] ?? '') ?>"
                    required
                >
            </div>

            <button type="submit" class="btn">
                Toegang tot Cursusmateriaal
            </button>
        </form>

        <div class="help-section">
            <h3>💡 Toegang tot je cursusmateriaal</h3>
            <ul>
                <li><strong>E-mailadres:</strong> Het adres waarmee je je hebt ingeschreven voor de cursus</li>
                <li><strong>Toegangscode:</strong> Je ontvangt deze per e-mail na inschrijving</li>
                <li><strong>Beschikbaarheid:</strong> Materiaal wordt beschikbaar de avond voor je cursus</li>
                <li><strong>Direct toegang:</strong> Klik op de link in je bevestigingsmail</li>
            </ul>
        </div>

        <div class="contact-section">
            <p>
                Problemen met toegang? 
                <a href="mailto:<?= ADMIN_EMAIL ?>?subject=Toegangsprobleem%20Cursistenportaal">
                    Neem contact op
                </a>
            </p>
        </div>
        
        <div class="footer-branding">
            Powered by Inventijn Learning Platform v2.2.0
        </div>
    </div>
</body>
</html>